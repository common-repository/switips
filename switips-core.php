<?php
/**
 * @package   Switips
 * @author    Outcode https://outcode.ru/
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 only
 */

class Switips{
  
  public $api_url;
  public $currencies = [];
  public $states = [
          'new',        // Создан
          'paid',       // Оплачен
          'canceled',   // Отменен
          'confirmed',  // Подтвержден
  ];

  public $url_without_domain = '/rest/partner/track';

  public function __construct($api_url, $currencies = null, $states = null){
    
    if((is_null($api_url) || $api_url == '') && $api_url != 'empty'){
      throw new Exception('Не указан url для запроса к API Switips'); 
    }
    
    $this->api_url = $api_url;
    
    if(is_array($currencies)){
      foreach($currencies as $currency){
        $this->currencies[] = $currency;
      }
    }else{
      $this->currencies = $this->getAvailableCurrencies($this->api_url);
    }

    if(is_array($states)){
      foreach($states as $state){
        $this->states[] = $state;
      }
    }     
  }
  
  public function campaignId($args){
    if(isset($args['campaign_id']) && is_numeric($args['campaign_id'])){
      return $args;
    }else{
      throw new Exception('campaign_id - должен быть числом'); 
    }
  }
  
  public function commissionAmount($args){
    if(isset($args['commission_amount'])){
      $args['commission_amount'] = str_replace(',','.', $args['commission_amount']);
      
      if(is_numeric($args['commission_amount'])){
        $args['commission_amount'] = number_format($args['commission_amount'], 2, '.', '');
        return $args;
      }else{
        throw new Exception('commission_amount - должен быть числом'); 
      }
    }else{
      throw new Exception('commission_amount - не указан'); 
    }
  }  

  public function currency($args){
    if(isset($args['currency']) && in_array($args['currency'], $this->currencies)){      
      return $args;
    }else{
      throw new Exception('currency - не допустимый тип валюты'); 
    }
  }
  
  public function getHashWithoutProducts($args){
    if(is_array($args)){
      $tmp = [
        'merchant_id' => $args['merchant_id'],
        'user_id' => $args['user_id'],
        'campaign_id' => $args['campaign_id'],
        'category_id' => $args['category_id'],
        'transaction_id' => $args['transaction_id'],
        'transaction_amount' => $args['transaction_amount'],
        'currency' => $args['currency'],
        'transaction_amount_currency' => $args['transaction_amount_currency'],
        'tt_date' => $args['tt_date'],
        'stat' => $args['stat'],   
        'secret_key' => $args['secret_key'],
      ];      

      $args['hash'] = MD5(implode('::', $tmp));
      return $args;
    }else{
      throw new Exception('Переден не массив. Нельзя сформировать hash'); 
    }
  }  
  
  public function merchantId($args){
    if(isset($args['merchant_id']) && $args['merchant_id'] != ''){      
      return $args;
    }else{
      throw new Exception('merchant_id - не указан');
    }
  }
  
  public function states($args){
    if(isset($args['stat']) && in_array($args['stat'], $this->states)){      
      return $args;
    }else{
      throw new Exception('stat - не допустимый статус'); 
    }
  }  
  
  public function transactionAmount($args){
    if(isset($args['transaction_amount'])){
      $args['transaction_amount'] = str_replace(',','.', $args['transaction_amount']);
      
      if(is_numeric($args['transaction_amount'])){
        $args['transaction_amount'] = number_format($args['transaction_amount'], 2, '.', '');
        return $args;
      }else{
        throw new Exception('transaction_amount - должен быть числом'); 
      }
    }else{
      throw new Exception('transaction_amount - не указан'); 
    }
  }
  
  public function transactionAmountCurrency($args){
    if(isset($args['transaction_amount_currency'])){
      $args['transaction_amount_currency'] = str_replace(',','.', $args['transaction_amount_currency']);
      
      if(is_numeric($args['transaction_amount_currency'])){
        $args['transaction_amount_currency'] = number_format($args['transaction_amount_currency'], 2, '.', '');
        return $args;
      }else{
        throw new Exception('transaction_amount_currency - должен быть числом'); 
      }
    }else{
      throw new Exception('transaction_amount_currency - не указан'); 
    }
  }  
  
  public function ttDate($args){
    if(isset($args['tt_date'])){
      $args['tt_date'] = date(DATE_ATOM, $args['tt_date']);
      return $args;
    }else{
      throw new Exception('tt_date - не указан');
    }
  }
  
  public function userId($args){
    if(isset($args['user_id']) && $args['user_id'] != ''){      
      return $args;
    }else{
      throw new Exception('user_id - не указан');
    }
  }
  
  public function getDefaultDomain($api_url = ''){
    if($api_url == ''){
      $json = file_get_contents('https://swwwp.s3.eu-central-1.amazonaws.com/d.json');

      if($json == false){
        return $api_url;
      }
      
      $json = json_decode($json);

      return 'https://'.$json->mirror->merchant_api.$this->url_without_domain;
    }
    
    return $api_url;
  }

  public function getAvailableCurrencies($api_url = ''){
    
    if($api_url == ''){
      $api_url = $this->api_url;
    }
    
    $url = parse_url($api_url);

    if(isset($url['scheme'])){
      $api_url = $url['scheme'].'://'.$url['host'].'/rest/partner/available_currencies';

      if ($curl = curl_init()) {
        curl_setopt($curl, CURLOPT_URL, $api_url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
          'Accept: */*'
        ]);

        $out = curl_exec($curl);
        curl_close($curl);
        
        if($out == false){
          return ['MXN', 'EUR', 'KZT', 'MDL', 'BYN', 'USD', 'UAH', 'RUB'];
        }

        return json_decode($out);
      }else{
        throw new Exception('cURL - не установлен');
      }
    }
  }

  public function sendRequestPost($args){

    if (is_array($args)) {

      $args = $this->campaignId($args);
      $args = $this->commissionAmount($args);      
      $args = $this->currency($args);
      $args = $this->merchantId($args);
      $args = $this->states($args);
      $args = $this->transactionAmount($args);
      $args = $this->transactionAmountCurrency($args);
      $args = $this->ttDate($args);
      $args = $this->userId($args);
      $args = $this->getHashWithoutProducts($args);

      $args = json_encode($args);
    }

    if ($curl = curl_init()) {
      curl_setopt($curl, CURLOPT_URL, $this->api_url);
      curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($curl, CURLOPT_POST, true);
      curl_setopt($curl, CURLOPT_POSTFIELDS, $args);
      curl_setopt($curl, CURLOPT_HTTPHEADER, array(
          'Content-Type: application/json',
      ));

      $out = curl_exec($curl);
      curl_close($curl);

      return json_decode($out);
    }else{
      throw new Exception('cURL - не установлен');
    }
  }
}
