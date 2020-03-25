<?php
require_once 'vendor/autoload.php';
header("Access-Control-Allow-Origin: *");
class InstagramApi
{
    private $email;
    private $user_data;
    private $mongodb;
    public function __construct($email_input, $uri)
    {
        $this->SetEmail($email_input);
        $this->SetUserData();
        $this->SetMongoDb($uri);
    }
    public function SetEmail($email_input)
    {
        $this->email = $email_input;
    }
    public function SetUserData()
    {
        $email = $this->email;
        $data = file_get_contents($email . '.json');
        $user_data_input = json_decode($data, true);
        $this->user_data = $user_data_input;
    }
    public function SetMongoDb($uri)
    {
        $client = new MongoDB\Client($uri);
        $this->mongodb = $client;
    }
    public function GetDatos($uri)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        $data = json_decode(curl_exec($ch), true);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http_code != '200') {
            //throw new Exception('Error : Failed to receieve access token');
            return null;
        } else {
            return $data;
        }
    }
    public function SetPublicacionesYComentarios()
    {
        //seteo colecciones mongo y datos de usuario
        $mongodb = $this->mongodb;
        $publicaciones = $mongodb->instagram->publicaciones;
        $comentarios = $mongodb->instagram->comentarios;
        $user_data = $this->user_data;
        //carga de listado de ids publicaciones
        foreach ($user_data['cuentas_instagram'] as $key => $value) {
            $token = $value['token'];
            $id_instagram = $value['id'];
            $url = 'https://graph.facebook.com/v6.0/' . $id_instagram . '/media?access_token=' . $token;
            $ids_publicaciones_instagram = $this->GetDatos($url);
            $ids_publicaciones_instagram['username'] = $value['username'];
            $ids_publicaciones_instagram['user_id'] = $id_instagram;
            $ids_publicaciones_instagram['email'] = $this->email;
            $ids_publicaciones_instagram['categoria'] = 'ids';
            $criterio = array('email' => $this->email, 'categoria' => 'ids');
            $seteo = array('$set' => $ids_publicaciones_instagram);
            $options = array('upsert' => true);
            $publicaciones->updateOne($criterio, $seteo, $options);
            //carga de datos de las publicaciones
            foreach ($ids_publicaciones_instagram['data'] as $key => $value) {
                $url = 'https://graph.facebook.com/v6.0/' . $value['id'] . '?fields=like_count,comments_count,comments,caption,timestamp,media_type,media_url,username&access_token=' . $token;
                $publicaciones_media = $this->GetDatos($url);
                $publicaciones_media['user_id'] = $id_instagram;
                $publicaciones_media['email'] = $this->email;
                $publicaciones_media['categoria'] = 'data';
                //carga estadisticas de las publicaciones
                if (isset($value['media_type'])) {
                    $metricas = 'engagement,impressions,reach,saved';
                    //IMAGE
                    if ($value['media_type'] === 'VIDEO') {
                        $metricas .= 'video_views';
                    } 
                    elseif ($value['media_type'] === 'CAROUSEL_ALBUM') {
                        $metricas = 'carousel_album_engagement,carousel_album_impressions,carousel_album_reach,carousel_album_saved,carousel_album_video_views';
                    }
                    $url = 'https://graph.facebook.com/v6.0/' . $value['id'] . '/insights?metric=' . $metricas . '&access_token=' . $token;
                    $estadisticas_media = $this->GetDatos($url);
                    $publicaciones_media['estadisticas'] = $estadisticas_media;
                }  
                $criterio = array('id' => $value['id'], 'categoria' => 'data');
                $seteo = array('$set' => $publicaciones_media);
                $options = array('upsert' => true);
                $publicaciones->updateOne($criterio, $seteo, $options);
                //carga de comentarios
                if (isset($publicaciones_media['comments'])) {
                    foreach ($publicaciones_media['comments']['data'] as $key => $value) {
                        $url = 'https://graph.facebook.com/v6.0/' . $value['id'] . '?fields=username,id,like_count,media,hidden,text,timestamp,user,replies&access_token=' . $token;
                        $com = $this->GetDatos($url);
                        $com['username_publicacion'] = $ids_publicaciones_instagram['username'];
                        $com['email'] = $this->email;
                        $com['categoria'] = 'data';
                        $criterio = array('id' => $value['id'], 'categoria' => 'data');
                        $seteo = array('$set' => $com);
                        $options = array('upsert' => true);
                        $comentarios->updateOne($criterio, $seteo, $options);
                    }
                }
            }
        }
    }
    public function GetPublicaciones()
    {
        try {
            $mongodb = $this->mongodb;
            $publicaciones = $mongodb->instagram->publicaciones;
            $email = $this->email;
            $query = ['email' => $email, 'categoria' => 'data'];
            return $publicaciones->find($query);
        } catch (\Throwable $th) {
            return null;
        }
    }
    public function GetComentarios()
    {
        try {
            $mongodb = $this->mongodb;
            $comentarios = $mongodb->instagram->comentarios;
            $email = $this->email;
            $query = ['email' => $email];
            return $comentarios->find($query);
        } catch (\Throwable $th) {
            return null;
        }
    }
}
