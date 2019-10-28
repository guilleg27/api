<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
require '../vendor/autoload.php'; 

$app = new \Slim\App;
$app->get('/albums', function (Request $request, Response $response, array $args) {
	$params    = $request->getQueryParams();
	$band_name = urlencode($params['q']);
	$url_auth  = "https://accounts.spotify.com/api/token";
	$url_api   = "https://api.spotify.com/v1";
   
    //auth spotify api
	$client_id     ='627f890037c54dd9a2266062778b969b';
	$client_secret ='bccfc74ad1844d27b7ef9a84209b8439';
	$auth          = base64_encode($client_id.":".$client_secret);

	$config = array('grant_type'=>'client_credentials');
    $options = array(
		CURLOPT_URL            => $url_auth,
		CURLOPT_POST           => true,
		CURLOPT_POSTFIELDS     => http_build_query($config),
		CURLOPT_HTTPHEADER     => array('Content-type: application/x-www-form-urlencoded', 'Authorization: Basic '.$auth),
		CURLOPT_RETURNTRANSFER => true
    );

	$curl   = curl_init();
	curl_setopt_array($curl,$options);
	$result = curl_exec($curl);
	curl_close($curl);
	$auth_data   = json_decode($result);
	$access_token = $auth_data->access_token;

	//Use the access token to access the Spotify Web API
	//get artist ID
	$url_artist = $url_api."/search?q=".$band_name."&type=artist";
    $options = array(
		CURLOPT_URL            => $url_artist,
		CURLOPT_POST           => false,
		CURLOPT_HTTPHEADER     => array('Content-type: application/x-www-form-urlencoded', 'Authorization: Bearer '.$access_token),
		CURLOPT_RETURNTRANSFER => true
    );

	$curl        = curl_init();
	curl_setopt_array($curl,$options);
	$result      = curl_exec($curl);
	curl_close($curl);
	$artist_data = json_decode($result);

	//uso criterio de banda con popularidad mas alta para el test
	$popularity = 0;
	foreach ($artist_data->artists as $key=>$artist) {
		foreach ($artist as $key => $values) {
			if ( (strtolower($values->name) == strtolower(urldecode($band_name))) && $values->popularity > $popularity )
			{
				$artist_id  = $values->id;
				$popularity = $values->popularity;
			}
		}
	}

	//get artist albums
	$url_albums = $url_api."/artists/".$artist_id."/albums?limit=50";
    $options = array(
		CURLOPT_URL            => $url_albums,
		CURLOPT_POST           => false,
		CURLOPT_HTTPHEADER     => array('Content-type: application/x-www-form-urlencoded', 'Authorization: Bearer '.$access_token),
		CURLOPT_RETURNTRANSFER => true
    );
	$curl        = curl_init();
	curl_setopt_array($curl,$options);
	$result      = curl_exec($curl);
	curl_close($curl);
	$albums_data = json_decode($result);

	$albums = array();
	if (isset($albums_data->error))
		return $data;
	else{
		$i=0;
		foreach ($albums_data->items as $key=>$values)
		{
			$albums[$i]["name"]     = $values->name;
			$albums[$i]["released"] = $values->release_date;
			$albums[$i]["tracks"]   = $values->total_tracks;
			foreach($values->images as $keyImg=>$valuesImg){
				if ($valuesImg->height==640 && $valuesImg->width==640)
				{
					$albums[$i]["cover"]["height"] = $valuesImg->height;
					$albums[$i]["cover"]["width"]  = $valuesImg->width;
					$albums[$i]["cover"]["url"]    = $valuesImg->url;		
				}
			}
			$i++;
		}
	}
	return $albums;

});
$app->run();