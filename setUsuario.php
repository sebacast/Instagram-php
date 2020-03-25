<?php
require_once('app-config.php');
header("Access-Control-Allow-Origin: *");
function GetApiGraph($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    $data = json_decode(curl_exec($ch), true);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http_code != '200') {
        return null;
    } else {
        return $data;
    }
}
$token = file_get_contents("php://input");//recupera el token temporal
$vec_usr = array();//Datos de usuario
$url = 'https://graph.facebook.com/v6.0/me?fields=id,name,email&access_token=' . $token;
$data = GetApiGraph($url);
$vec_usr['email'] = $data['email'];
$vec_usr['id'] = $data['id'];
$vec_usr['nombre'] = $data['name'];
//FB pags de usuario
$url = 'https://graph.facebook.com/' . $data['id'] . '/accounts?access_token=' . $token;
$data = GetApiGraph($url);
$cont = 0;
$c = 0;
//recupera las paginas de facebook con las cuentas de instagram asociadas
foreach ($data['data'] as $key => $value) {
    //Token de larga duracion
    $url = 'https://graph.facebook.com/v6.0/oauth/access_token?grant_type=fb_exchange_token&client_id=' . client_id . '&client_secret=' . client_secret . '&fb_exchange_token=' . $token;
    $json = GetApiGraph($url);
    $token = $json['access_token'];
    //Token permanente
    //$url = 'https://graph.facebook.com/'.$value['id'].'?fields=access_token&access_token='.$token;
    //$json = GetApiGraph($url);
    //$token = $json['access_token'];

    $vec_usr['paginas'][$c]['nombre'] = $value['name'];
    $vec_usr['paginas'][$c]['id'] = $value['id'];
    $c++;
    $url = 'https://graph.facebook.com/v6.0/' . $value['id'] . '?fields=instagram_business_account&access_token=' . $token;
    $datas = GetApiGraph($url);
    if ($datas['instagram_business_account']['id'] !== null) {
        $id_instagram = $datas['instagram_business_account']['id'];
        //Datos de la cuenta de instagram
        $url2 = 'https://graph.facebook.com/v6.0/' . $id_instagram . '?fields=name,username,biography,profile_picture_url,followers_count,follows_count,media_count&access_token=' . $token;
        $datos_instagram = GetApiGraph($url2);
        $vec_usr['cuentas_instagram'][$cont] = $datos_instagram;
        $vec_usr['cuentas_instagram'][$cont]['token'] = $token;
        $cont++;
    }
}
//almacena el vector resultante en un archivo json con el email del usuario
$file = getcwd() . '/' . $vec_usr['email'] . '.json';
file_put_contents($file, json_encode($vec_usr));
