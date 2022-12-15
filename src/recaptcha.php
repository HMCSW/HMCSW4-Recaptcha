<?php

namespace hmcswModule\recaptcha\src;

use hmcsw\service\config\ConfigService;
use hmcsw\service\templates\AssetsService;
use hmcsw\service\templates\LanguageService;
use hmcsw\service\authorization\SessionService;
use hmcsw\service\module\moduleCaptchaRepository;

class recaptcha implements moduleCaptchaRepository
{

  private array $config;

  public function __construct ()
  {
    $this->config = json_decode(file_get_contents(__DIR__.'/../config/config.json'), true);
  }

  public function createCaptcha (): void
  {
    $nightMode = SessionService::$nightMode;
    $language = explode("_", LanguageService::getCurrentLanguage()['key'])[0];

    AssetsService::addJS("
      <script type='text/javascript' xmlns='http://www.w3.org/1999/html'>
        const mode = '" . $nightMode . "';
        
        var theme;
        if(mode === 'true'){
          theme = 'dark';
        } else if (mode === 'false' ){
          theme = 'light';
        } else if (mode === 'device' ){
          if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            theme = 'dark';
          } else {
            theme = 'light';
          }
        } else {
          theme = 'light';
        }
        console.log(theme);
          var onloadCallback = function() {
              grecaptcha.render('recaptcha', {
                  'sitekey' : '" . $this->getConfig()['key']['public'] . "',
                  'theme' : theme,
                  'lang' : '" . $language . "',
              });
          };
      </script>
    ");

    AssetsService::addJS("
      <script src='https://www.google.com/recaptcha/api.js?onload=onloadCallback&hl=" . $language . "
              async defer'>
      </script>
      ");
  }

  public function getConfig (): array
  {
    return $this->config;
  }

  public function validCaptcha ($response): array
  {
    $url = 'https://www.google.com/recaptcha/api/siteverify?secret=' . $this->getConfig()['key']['secret'] . '&response=' . urlencode($response);

    $curl = curl_init();
    curl_setopt_array($curl, [CURLOPT_URL => $url, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 1, CURLOPT_TIMEOUT => 5,]);
    $response = curl_exec($curl);
    if ($response === false) {
      return ["success" => false, "response" => "request error"];
    }
    $response = json_decode($response, true);

    if ($response["success"]) {
      return ["success" => true, "response" => []];
    } else {
      return ["success" => false, "response" => ["error_code" => 400, "error_message" => "failed", "error_response" => $response]];
    }
  }

  public function startModule (): bool
  {
    if($this->config['enabled']){
      return true;
    } else {
      return false;
    }
  }

  public function getMessages (string $lang): array|bool
  {
    if (!file_exists(__DIR__ . '/../messages/' . $lang . '.json')) {
      return false;
    }

    return json_decode(file_get_contents(__DIR__ . '/../messages/' . $lang . '.json'), true);
  }

  public function getModuleInfo(): array
  {
    return json_decode(file_get_contents(__DIR__.'/../module.json'), true);
  }

  public function getProperties(): array
  {
    return [];
  }

  public function getCaptchaResponse (): string
  {
    return $_POST['g-recaptcha-response'] ?? "";
  }
}