<?php
/*
///////////////////////////////////////////////////////////////
	Lightning adapter for memeracing.net
	javier@clobig.com
///////////////////////////////////////////////////////////////
	
lightning.php
   	PHP file contaning functions to handle invoices
lightning.js
	Javascript file to handle AJAX requests
lightning.css
	Style to handle views


Syntax:
         http://memeracing.com/lightning.php
               Method: POST
			   Parameters: Action    = 'getinvoice'
			               Amount    = xxxxx (satoshis)
						   Message   = 'Some Text'
               Returns: JSON {"Invoice":"xxxx","Expiry":"yyyy","r_hash_str":"zzzz"}
		   		                xxxx = LN Payment request
						yyyy = expiry seconds
						zzzz = HEX representation of 32 byte base64 r_hash
*/ 
include("config.php");


function getPaymentRequest($memo='',$satoshi=0,$expiry){
 $data = json_encode(array("memo"   => "$memo",
                           "value"  => "$satoshi",
			   "expiry" =>  $expiry
			  )     
                     ); 					 
 $ch = curl_init("https://127.0.0.1:8080/v1/invoices");
 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
 curl_setopt($ch, CURLOPT_POST, 1);
 curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
 curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
 curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Grpc-Metadata-macaroon: $macaroon"
    ));
 $response = curl_exec($ch);
 curl_close($ch);
 return json_decode($response);
}

function lookupInvoice($r_hash_str){
 $ch = curl_init("https://127.0.0.1:8080/v1/invoice/$r_hash_str");
 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);	
 curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
 curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Grpc-Metadata-macaroon: $macaroon"
    ));
 $response = curl_exec($ch);
 curl_close($ch);
 return json_decode($response);
}

function waitInvoice($add_index){
/* curl --insecure --header "Grpc-Metadata-macaroon: "MACAROON_HEX" -X GET https://127.0.0.1:8080/v1/invoices/subscribe */
 $ch = curl_init("https://127.0.0.1:8080/v1/invoices/subscribe ".$add_index);
 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
 curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
 curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Grpc-Metadata-macaroon: $macaroon"
    ));
 $response = curl_exec($ch);
 curl_close($ch);
 return json_decode($response);
}

function decodeInvoice($payreq){
/* curl --insecure --header "Grpc-Metadata-macaroon: "MACAROON_HEX" -X GET https://127.0.0.1:8080/v1/payreq/payreq */
 $ch = curl_init("https://127.0.0.1:8080/v1/payreq/".$payreq);
 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
 curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
 curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Grpc-Metadata-macaroon: $macaroon"
    ));
 $response = curl_exec($ch);
 curl_close($ch);
 return json_decode($response);
}


//$PR = getPaymentRequest('test4','4', '1800');
//echo json_encode(array('Invoice'   =>$PR->payment_request,
//                               'Expiry'    =>EXPIRY,
//                                                   'r_hash_str'=>bin2hex(base64_decode($PR->r_hash))
//                                                   )
//                                     );
