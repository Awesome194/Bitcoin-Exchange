<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\BitMix;
use Illuminate\Support\Str;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use App\Mix;
use Storage;
use Log;
use Carbon\Carbon;
use Mail as Mail;

class StartMixingController extends Controller
{
	public function createOrder(Request $request){
		$addresses 		= [];
		$percentages 	= [];
		$delays			= [];
		// $coin 			= (strtolower($request->currency) == 'btc') ? 'bitcoin' : 'litecoin';
		if (strtolower($request->currency) == 'btc'){
			$coin='bitcoin';
		}
		if (strtolower($request->currency) == 'ltc'){
			$coin='litecoin';
		}
		if (strtolower($request->currency) == 'dash'){
			$coin='dash';
		}
		foreach ($request->output as $key => $value) {
			array_push($addresses, $value['address']);
			array_push($percentages, $value['percentage']);
			array_push($delays, $value['delay']);
		}
		// create an instance with unique code
		$mixer = new Bitmix( $coin , Str::random(12),env('BITMIX_PARTNER_KEY'));

		$mixer
		    // set delay in minutes for each address
		    ->setDelay($delays)
		    // set random fee between 0.5 and 3.5
		    ->setFee($request->serviceFee)
		    // set distribution percentage
		    ->setDistribution($percentages)
		    // set receive addresses
		    ->setReceiveAddresses($addresses);


		// if you wanna use TOR mirror with API
		//$mixer->switchToTor('192.168.0.1:9050');

		$order = $mixer->createOrder();
		if(!$order->isCreated())
		    print_r($order->getErrors());
		else {
            /* Sending the request */
            $url = 'https://chart.googleapis.com/chart?chs=225x225&chld=L|2&cht=qr&chl='.$coin.':'.$order->getInputAddress();
            $client = new Client();
            $response = $client->get($url);
            $qr_code = $response->getBody()->getContents();
            $qr_code_base64 = 'data:image/' . 'png' . ';base64,' . base64_encode($qr_code);
            // header('Content-Type: image/png');
            // echo $qr_code;

            $order_status = $mixer->getOrder($order->getId())->getAll();
            $txt = $order->getInputAddress().'.txt';
            Mix::Create([
					        'order_id'  	=> $order->getId(),
					        'code'    		=> $order->getCode(),
					        'status'       	=> $order_status->order->status,
					        'coin'       	=> $order_status->order->coin,
					        'input_address'	=> $order->getInputAddress(),
					        'qrcode'       	=> $qr_code_base64,
					        'txt'			=> $txt,
					    ]);

            Storage::disk('agreements')->put('public/agreements/'.$txt, (string)$mixer->getLetter($order->getId()));

			// dd($order->getAll(), $mixer->getOrder($order->getId())->getAll(), (string)$mixer->getLetter($order->getId()));
		    // print_r($order->getAll());
		    // print_r($mixer->getOrder($order->getId())->getAll());
		    // print_r((string)$mixer->getLetter($order->getId()));
		    // print_r($mixer->deleteOrder($order->getId())->getData());
		    return response()->json(['success' => 'success', 'data' => [ 'link' => $order->getId() ]]);
		}

	}

	public function confirmOrder(Request $request){
		$order = Mix::where('order_id', $request->orderId)->firstOrFail();
		return response()->json(['success' => 'success', 'qrcode' => $order->qrcode]);
	}

	public function showQrPage(Request $request, $locale, $address){
		//dd($address);
		$order = Mix::where('order_id', $address)->firstOrFail();
		if( Carbon::now()->diffInHours($order->updated_at) > 23 ){
			return abort(404);
		}
		$mixer = new Bitmix( 'litecoin' , Str::random(12));
		$order_status = ($mixer->getOrder($order->order_id)->getAll());
		//dd($order_status);
		return view('start-mixing', compact('order', 'order_status'));
	}

	public function checkOrder(Request $request){
		$order = Mix::where('order_id', $request->orderId)->firstOrFail();
		$mixer = new Bitmix( 'litecoin' , Str::random(12));
		$order_status = $mixer->getOrder($request->orderId)->getAll();
		Log::info(print_r($order_status, true));

		$individual_sent = array();
		foreach ($order_status->outputs as $key => $value) {
			array_push($individual_sent, number_format($value->sent,8));
		}

		if($order_status->order->status != $order->status){
			if(strtolower($order_status->order->status) == 'processing'){
				$order->status = $order_status->order->status;
				$order->update();
				return response()->json(['success' => 'Processing in progress!', 'stage' => 1, 'sent' => number_format($order_status->order->sent,8), 'individual_sent' => $individual_sent ]);
			}elseif(strtolower($order_status->order->status) == 'done'){
				$order->status = $order_status->order->status;
				$order->update();

				return response()->json(['success' => 'Order completed!', 'stage' => 2, 'sent' => number_format($order_status->order->sent,8), 'individual_sent' => $individual_sent ]);
			}
		}

		return response()->json(['stage' => 0, 'sent' => number_format($order_status->order->sent,8), 'individual_sent' => $individual_sent ]);

	}

    public function mail(Request $request){
    	$message = 
    	'<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
			   "http://www.w3.org/TR/html4/loose.dtd">
			<html lang="en">

			<head>
			    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
			    <meta name="viewport" content="width=device-width, initial-scale=1">
			    <!-- So that mobile will display zoomed in -->
			    <meta http-equiv="X-UA-Compatible" content="IE=edge">
			    <!-- enable media queries for windows phone 8 -->
			    <meta name="format-detection" content="telephone=no">
			    <!-- disable auto telephone linking in iOS -->
			    <title></title>

			</head>
			<style type="text/css">
			    .header,
			    .title,
			    .subtitle,
			    .footer-text {
			        font-family: Helvetica, Arial, sans-serif;
			    }
			    
			    .header {
			        font-size: 24px;
			        font-weight: bold;
			        padding-bottom: 12px;
			        color: #DF4726;
			    }
			    
			    .footer-text a {
			        color: #aaaaaa;
			    }
			    
			    .container {
			        width: 7000px;
			        max-width: 700px;
			    }
			    
			    .container-padding {
			        padding-left: 24px;
			        padding-right: 24px;
			    }
			    
			    .content {
			        padding-top: 12px;
			        padding-bottom: 12px;
			        background-color: #ffffff;
			    }
			    
			    code {
			        background-color: #eee;
			        padding: 0 4px;
			        font-family: Menlo, Courier, monospace;
			        font-size: 12px;
			    }
			    
			    hr {
			        border: 0;
			        border-bottom: 1px solid #cccccc;
			    }
			    
			    .hr {
			        height: 1px;
			        border-bottom: 1px solid #cccccc;
			    }
			    
			    .subtitle {
			        font-size: 16px;
			        font-weight: 600;
			        color: #2469A0;
			    }
			    
			    .subtitle span {
			        font-weight: 400;
			        color: #999999;
			    }
			    
			    .title {
			        font-size: 20px;
			        font-weight: 600;
			        color: #374550;
			    }
			    
			    .body-text,
			    .footer-text {
			        font-family: Helvetica, Arial, sans-serif;
			        font-size: 14px;
			        line-height: 20px;
			        text-align: left;
			        color: #333333;
			    }
			    
			    .socialIcon {
			        max-width: 110px;
			        margin: 15px auto 0 auto;
			    }
			    
			    .socialIcon .s1 {
			        border: 0;
			        padding: 0px;
			        /*background: #034d77;*/
			        margin-left: 10px;
			        border-radius: 2px;
			        width: 25px;
			    }
			    
			    #message-htmlpart1 div.rcmBody .socialIcon {
			        max-width: 110px;
			        margin: 25px auto 15px auto;
			        display: inline;
			    }
			    
			    #message-htmlpart1 div.rcmBody .socialIcon .s1 {
			        border: 0;
			        padding: 0px;
			        margin-left: 10px;
			        border-radius: 2px;
			        width: 25px;
			    }
			    
			    @media screen and (max-width: 599px) {
			        .force-row,
			        .container {
			            width: 100% !important;
			            max-width: 100% !important;
			        }
			    }
			    
			    @media screen and (max-width: 400px) {
			        .container-padding {
			            padding-left: 12px !important;
			            padding-right: 12px !important;
			        }
			    }
			</style>

			<body style="margin:0; padding:0;" bgcolor="#F0F0F0" leftmargin="0" topmargin="0" marginwidth="0" marginheight="0">

			    <!-- 100% background wrapper (grey background) -->
			    <table border="0" width="100%" height="100%" cellpadding="0" cellspacing="0" bgcolor="#F0F0F0">
			        <tr>
			            <td align="center" valign="top" bgcolor="#F0F0F0" style="background-color: #F0F0F0;">

			                <br>

			                <!-- 600px container (white background) -->
			                <table border="0" width="600" cellpadding="0" cellspacing="0" class="container" style="margin-top: 7%;
			    text-align: center;  box-shadow: 2px 2px 4px 0px #ddd; background: #fff; margin-bottom: 7%; ">

			                    <tr>
			                        <td class="container-padding content" align="center" style="padding: 0px;">
			                            <div class="title" style=" background: #07b8fe; margin-bottom: 15px;">
			                                <img src="https://i.imgur.com/Vlz62lA.gif" alt="Smart Mixer" style="max-width: 150px; margin-top: 15px; margin-bottom: 10px; color:white;">
			                            </div>

			                            <br>
			                            <div class="title">Smart Mixer Support mail by {{user_name}} </div>
			                            <br>
			                            <br>

			                            <div class="body-text" style="text-align: center; padding: 15px;">
			                                <p>{{message}}</p>
			                                <br>
			                                <p class="margin-bottom: 5px;">Letter Of Guarantee</p>
			                                <p>{{letter}}</p>
			                            </div>

			 
			                        </td>
			                    </tr>

			                </table>
			                <!--/600px container -->

			            </td>
			        </tr>
			    </table>
			    <!--/100% background wrapper-->

			</body>

			</html>';

        $template = str_replace("{{user_name}}", $request['support-name'] , $message);
        $mm = str_replace("{{message}}", $request['support-message'] , $template);
        $message = str_replace("{{letter}}", $request['letter-content'], $mm);
        $to = env('SENDER_EMAIL_ADDRESS');
        $from = 'support@smartmix.com';
        $subject = 'Support Email by '.$request['support-name'];
        $name = $request['support-name'];
        Mail::send(['html' => 'email'], ['text' => $message],
                function ($message) use ($to, $name, $from, $subject) {
                    $message->to($to)
                    ->from($from)
                    ->subject($subject);
                }
        );

		return response()->json(['success' => 'Email successfully sent!']);

        // Mail::send($view, $data, function($message) use ($request) {
        //     $m->to('ali.keydevs@gmail.com',  'Muammad Ali');
        //     $m->from('support@mail.com', $request['support-name'] ); 
        //     $m->subject($request['support-message']. "\r\n\r\n" . "Letter of Guarantee\r\n". $request['letter-content']);
        // });

    }


}
