<?php

require_once __DIR__ .'/../Libraries/Envapi.php';
require_once __DIR__ .'/../Config/Item.php';

require_once __DIR__.'/../vendor/autoload.php';

use RestApi\Libraries\Envapi;
use \WpOrg\Requests\Requests as Requests;
use Firebase\JWT\JWT;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;

\WpOrg\Requests\Autoload::register();

function getUserIP()
{
	$ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    } else {
        $ipaddress = 'UNKNOWN';
    }

    return $ipaddress;
}

$envato_res = Envapi::getPurchaseData($item_purchase_code);

if (empty($envato_res)) {
    $return = ['status'=>false, 'message'=>'Something went wrong'];
    return;
}
if (!empty($envato_res->error)) {
    $return = ['status'=>false, 'message'=>$envato_res->description];
    return;
}
if (empty($envato_res->sold_at)) {
    $return = ['status'=>false, 'message'=>'Sold time for this code is not found'];
    return;
}
if ((false === $envato_res) || !is_object($envato_res) || isset($envato_res->error) || !isset($envato_res->sold_at)) {
    $return = ['status'=>false, 'message'=>'Something went wrong'];
    return;
}

$item_config = new \RestApi\Config\Item();
if ($item_config->product_item_id != $envato_res->item->id) {
    $return = ['status'=>false, 'message'=>'Purchase key is not valid'];
    return;
}

$request = \Config\Services::request();
$agent_data = $request->getUserAgent();


$Settings_model = model("App\Models\Settings_model");

$data['user_agent']       = $agent_data->getBrowser().' '.$agent_data->getVersion();
$data['activated_domain'] = base_url();
$data['requested_at']     = date('Y-m-d H:i:s');
$data['ip']               = getUserIP();
$data['os']               = $agent_data->getPlatform();
$data['purchase_code']    = $item_purchase_code;
$data['envato_res']       = $envato_res;
$data                     = json_encode($data);
try {
    $headers = ['Accept' => 'application/json'];
    $request = Requests::post(REG_PROD_POINT, $headers, $data);
    if ((500 <= $request->status_code) && ($request->status_code <= 599) || 404 == $request->status_code) {

        $Settings_model->save_setting($product.'_verification_id', '');
        $Settings_model->save_setting($product.'_verified', true);
        $Settings_model->save_setting($product.'_last_verification', time());

        $return = ['status'=>true];
        return;
    }

    $response = json_decode($request->body);
    if (200 != $response->status) {
        $return = ['status'=>false, 'message'=>$response->message];
        return;
    }

    if (200 == $response->status) {
        $return = $response->data ?? [];
        if (!empty($return)) {
            $Settings_model->save_setting($product.'_verification_id', $return->verification_id);
            $Settings_model->save_setting($product.'_verified', true);
            $Settings_model->save_setting($product.'_last_verification', time());
            file_put_contents(__DIR__.'/../Config/token.php', $return->token);

            $return = ['status'=>true];
            return;
        }
    }
} catch (Exception $e) {
    $Settings_model->save_setting($product.'_verification_id', '');
    $Settings_model->save_setting($product.'_verified', true);
    $Settings_model->save_setting($product.'_last_verification', time());

    $return = ['status'=>true];
    return;
}

$return = ['status'=>false, 'message'=>'Something went wrong'];
return;
