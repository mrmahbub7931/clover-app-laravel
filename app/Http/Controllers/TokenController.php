<?php

namespace App\Http\Controllers;

use App\Models\Token;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class TokenController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public $client_id = 'TA616H8YGW1CR';
    public $m_id = '1TJMNFD8V8P71';
    public $appSecret = 'b9123d91-6de3-51d7-36ab-f26378cc2cd6';

    public static function generateRandomString($length = 40) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public static function genarate_api_key($length = 40) {
        $api_key = self::generateRandomString($length);
        $token = Token::where('api_key', $api_key)->first();
        if (is_null($token)) {
            return $api_key;
        }else{
            return self::genarate_api_key();
        }
    }
    //usage 
    // $myRandomString = generateRandomString(40);
    public function index(Request $request)
    {
        // $client_id = 'TA616H8YGW1CR';
        // $appSecret = 'b9123d91-6de3-51d7-36ab-f26378cc2cd6';
        $params = $request->all();
        // dd($params);
        if (count($params) > 0) {
            // $credentials = Token::where('m_id', $params["merchant_id"])->first();
            $credentials = DB::table('tokens')->where('m_id', $params["merchant_id"])->first();
           
            return view('api.api_key', compact('credentials'));
        }else{

            if(array_key_exists("code", $params)) {
                $mid = $params["merchant_id"];
                $code = $params["code"];
                // You'll find this in your app's settings page.
                $ch = curl_init();
                $vars = array(
                    '{$appId}'=> $params["client_id"],
                    '{$appSecret}'=> $this->appSecret,
                    '{$codeUrlParam}'=> $params['code']
                );
                $url = strtr('https://sandbox.dev.clover.com/oauth/token?client_id={$appId}&client_secret={$appSecret}&code={$codeUrlParam}', $vars);
                try {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    $result = curl_exec($ch);
                    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    if ($httpcode == 200) {
                        $oauth = json_decode($result, true)['access_token'];
                        
                        try{

                        //Store oauth/merchantId in session for 1hr. (in cookie)
                        $token = Token::where('m_id', $mid)->first();
                        if (!is_null($token)) {
                            $token->token = $oauth;
                            $token->save();
                        }else{
                            $api_key = self::genarate_api_key();
                            $token = Token::insert([
                                'token' => $oauth,
                                'm_id' => $mid,
                                'api_key' => $api_key
                            ]);   
                        }

                        }catch (Exception $e) {
                        print_r("failed to insert into session");
                        }
                    }else {
                        return redirect('https://sandbox.dev.clover.com/oauth/authorize?client_id='.$this->client_id.'&redirect_uri=https://clover.lefleuriv.com/');
                    }
                } catch (HttpException $ex) {
                    echo $ex;
                } finally {
                    curl_close($ch);
                }
            }else {
                try{
                    return redirect('https://sandbox.dev.clover.com/oauth/authorize?client_id='.$this->client_id.'&redirect_uri=https://clover.lefleuriv.com/');
                }catch(Exception $e) {
                    print_r("Error, could not redirect to www.clover.com");
                }
            }
        }

        
    }


    public function get_order(Request $request)
    {
        $params = $request->all();
        $final_query = "";
        if(!isset($params["api_key"]) || $params["api_key"] ==""){
            $responseArr = [
                "err_msg" => "Api Key neeeded",
                "status" => "401"
            ];
            return response($responseArr);
        }
        if(isset($params["args"])){
            $final_query = http_build_query($params["args"]);
        }
        $results = DB::table('tokens')->where('api_key', $params["api_key"] )->first();
        if ($results) {
            // return $results->token;
            $oauth = $results->token;
            $mid = $results->m_id;
            $ch = curl_init();
            $url = 'https://apisandbox.dev.clover.com/v3/merchants/' . $mid . '/orders';
            $final_url = $url."?".$final_query;
            curl_setopt($ch, CURLOPT_URL, $final_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $oauth,
            'Content-Type: application/json'
            ));
            $result = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            $responseArr = [
                "response" => $result,
                "status" => $httpcode
            ];
            return response($responseArr);
            // print($result);
            // print($httpcode);
            // print($oauth);
        }else {
            $responseArr = [
                "err_msg" => "API Key Invelid",
                "status" => "401"
            ];
            return response($responseArr);
        }
        
    }
// Product Export
    public function post_inventory(Request $request)
    {
        $params = $request->all();
        $final_parames = "";
        if(!isset($params["api_key"]) || $params["api_key"] ==""){
            $responseArr = [
                "err_msg" => "Api Key neeeded",
                "status" => "401"
            ];
            return response($responseArr);
        }
        
        if(isset($params["args"]) && is_string($params["args"])){
            $final_parames = $params["args"];
        }else{
            $responseArr = [
                "err_msg" => "No item data",
                "status" => "400"
            ];
            return response($responseArr);
        }
        $results = DB::table('tokens')->where('api_key', $params["api_key"] )->first();
        
        if ($results) {

            $oauth = $results->token;
            $mid = $results->m_id;
            $ch = curl_init();
            $url = 'https://apisandbox.dev.clover.com/v3/merchants/' . $mid . '/items';
            $final_url = $url;
            curl_setopt($ch, CURLOPT_URL, $final_url);
            // curl_setopt($ch,CURLOPT_POST, count($final_parames));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $final_parames);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $oauth,
            'Content-Type: application/json'
            ));
            $result = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            $responseArr = [
                "response" => $result,
                "status" => $httpcode
            ];
            return response($responseArr);
            // print($result);
            // print($httpcode);
            // print($oauth);
        }else {
            $responseArr = [
                "err_msg" => "API Key Invalid",
                "status" => "401"
            ];
            return response($responseArr);
        }
        
    }
    // Product Export
    public function update_inventory(Request $request)
    {
        $params = $request->all();
        $final_parames = "";
        if(!isset($params["api_key"]) || $params["api_key"] ==""){
            $responseArr = [
                "err_msg" => "Api Key neeeded",
                "status" => "401"
            ];
            return response($responseArr);
        }
        if(!isset($params["item_id"]) && is_string($params["item_id"])){
            $responseArr = [
                "err_msg" => "No item ID given",
                "status" => "400"
            ];
            return response($responseArr);
        }
        if(isset($params["args"]) && is_string($params["args"])){
            $final_parames = $params["args"];
        }else{
            $responseArr = [
                "err_msg" => "No item data",
                "status" => "400"
            ];
            return response($responseArr);
        }
        $results = DB::table('tokens')->where('api_key', $params["api_key"] )->first();
        
        if ($results) {

            $oauth = $results->token;
            $mid = $results->m_id;
            $ch = curl_init();
            $url = 'https://apisandbox.dev.clover.com/v3/merchants/' . $mid . '/items/'. $params["item_id"];
            $final_url = $url;
            curl_setopt($ch, CURLOPT_URL, $final_url);
            // curl_setopt($ch,CURLOPT_POST, count($final_parames));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $final_parames);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $oauth,
            'Content-Type: application/json'
            ));
            $result = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            $responseArr = [
                "response" => $result,
                "status" => $httpcode
            ];
            return response($responseArr);
            // print($result);
            // print($httpcode);
            // print($oauth);
        }else {
            $responseArr = [
                "err_msg" => "API Key Invalid",
                "status" => "401"
            ];
            return response($responseArr);
        }
        
    }
// Product Delete
    public function deleteItem(Request $request)
    {
        $params = $request->all();
        $clover_item_id = "";
        if(!isset($params["api_key"]) || $params["api_key"] ==""){
            $responseArr = [
                "err_msg" => "Api Key neeeded",
                "status" => "401"
            ];
            return response($responseArr);
        }
        
        if(isset($params["args"]) && is_string($params["args"])){
            $clover_item_id = $params["args"];
        }else{
            $responseArr = [
                "err_msg" => "No item data",
                "status" => "400"
            ];
            return response($responseArr);
        }
        $results = DB::table('tokens')->where('api_key', $params["api_key"] )->first();

        
        if ($results) {
            $oauth = $results->token;
            $mid = $results->m_id;
            
            $ch = curl_init();
            $url = 'https://sandbox.dev.clover.com/v3/merchants/'.$mid.'/items/'.$clover_item_id.'';
            $final_url = $url;
            curl_setopt($ch, CURLOPT_URL, $final_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $oauth,
            'Content-Type: application/json'
            ));
            $result = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $responseArr = [
                "response" => $result,
                "status" => $httpcode
            ];
            return response($responseArr);
        }else {
            $responseArr = [
                "err_msg" => "API Key Invalid",
                "status" => "401"
            ];
            return response($responseArr);
        }
    }

    /**
     * ignore
     */

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreTokenRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreTokenRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Token  $token
     * @return \Illuminate\Http\Response
     */
    public function show(Token $token)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Token  $token
     * @return \Illuminate\Http\Response
     */
    public function edit(Token $token)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateTokenRequest  $request
     * @param  \App\Models\Token  $token
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateTokenRequest $request, Token $token)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Token  $token
     * @return \Illuminate\Http\Response
     */
    public function destroy(Token $token)
    {
        //
    }
}
