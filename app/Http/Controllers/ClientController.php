<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Plan;
use App\Models\Server;
use Symfony\Component\Yaml\Yaml;

class ClientController extends Controller
{
    public function subscribe (Request $request) {
        $user = $request->user;
        $server = [];
        if ($user->expired_at > time()) {
          $servers = Server::all();
          foreach ($servers as $item) {
              $groupId = json_decode($item['group_id']);
              if (in_array($user->group_id, $groupId)) {
                  array_push($server, $item);
              }
          }
        }
        if(strpos($_SERVER['HTTP_USER_AGENT'], 'Quantumult%20X') !== false) {
          die($this->quantumultX($user, $server));
        }
        if(strpos($_SERVER['HTTP_USER_AGENT'], 'Quantumult') !== false) {
          die($this->quantumult($user, $server));
        }
        if(strpos($_SERVER['HTTP_USER_AGENT'], 'clash_win') !== false) {
          die($this->clash($user, $server));
        }
        die($this->origin($user, $server));
    }

    private function quantumultX ($user, $server) {
      $uri = '';
      foreach($server as $item) {
        $uri .= "vmess=".$item->host.":".$item->port.", method=none, password=".$user->v2ray_uuid.", over-tls=".($item->tls?'true':'false').", certificate=0, fast-open=false, udp-relay=false, tag=".$item->name."\r\n";
      }
      return base64_encode($uri);
    }

    private function quantumult ($user, $server) {
      $uri = '';
      header('subscription-userinfo: upload='.$user->u.'; download='.$user->d.';total='.$user->transfer_enable);
      foreach($server as $item) {
        $uri .= "vmess://".base64_encode($item->name.'= vmess, '.$item->host.', '.$item->port.', chacha20-ietf-poly1305, "'.$user->v2ray_uuid.'", over-tls='.($item->tls?"true":"false").', certificate=0, group='.config('v2board.app_name', 'V2Board'))."\r\n";
      }
      return base64_encode($uri);
    }

    private function origin ($user, $server) {
      $uri = '';
      foreach($server as $item) {
        $config = [
          "ps" => $item->name,
          "add" => $item->host,
          "port" => $item->port,
          "id" => $user->v2ray_uuid,
          "aid" => "2",
          "net" => "tcp",
          "type" => "chacha20-poly1305",
          "host" => "",
          "tls" => $item->tls?"tls":"",
        ];
        $uri .= "vmess://".base64_encode(json_encode($config))."\r\n";
      }
      return base64_encode($uri);
    }

    private function clash ($user, $server) {
      $proxy = [];
      $proxyGroup = [];
      $proxies = [];
      foreach ($server as $item) {
        $array = [];
        $array['name'] = $item->name;
        $array['type'] = 'vmess';
        $array['server'] = $item->host;
        $array['port'] = $item->port;
        $array['uuid'] = $user->v2ray_uuid;
        $array['alterId'] = $user->v2ray_alter_id;
        $array['cipher'] = 'auto';
        if ($item->tls) {
          $array['tls'] = true;
        }
        array_push($proxy, $array);
        array_push($proxies, $item->name);
      }
      array_push($proxyGroup, [
        'name' => config('v2board.app_name', 'V2Board'),
        'type' => 'select',
        'proxies' => $proxies
      ]);
      
      $config = [
        'port' => 7890,
        'socks-port' => 0,
        'allow-lan' => false,
        'mode' => 'Rule',
        'log-level' => 'info',
        'external-controller' => '0.0.0.0:9090',
        'secret' => '',
        'Proxy' => $proxy,
        'Proxy Group' => $proxyGroup,
        'Rule' => [
          'DOMAIN-SUFFIX,google.com,'.config('v2board.app_name', 'V2Board'),
          'DOMAIN-KEYWORD,google,'.config('v2board.app_name', 'V2Board'),
          'DOMAIN,google.com,'.config('v2board.app_name', 'V2Board'),
          'DOMAIN-SUFFIX,ad.com,REJECT',
          'IP-CIDR,127.0.0.0/8,DIRECT',
          'GEOIP,CN,DIRECT',
          'MATCH,'.config('v2board.app_name', 'V2Board')
        ]
      ];
      return Yaml::dump($config);
    }
}
