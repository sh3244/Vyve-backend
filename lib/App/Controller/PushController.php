<?php
/**
 * User: Sasaki Kenski
 * Date: 2016-03-03
 */

namespace App\Controller;

class PushController
{
    public function __construct()
    {
    }

    private function sendPushMessage($user_id, $title, $data)
    {
        $userInform = UserController::getUserInformSimple($user_id);

        if ($userInform['platform'] == 0) {
            $result = $this->sendPushToIOS($userInform['dev_uuid'], $title, $data);
        } else {
            $result = $this->sendPushToAndroid($userInform['dev_uuid'], $title, $data);
        }

        return $result;
    }

    /**
     * Send Push to Android devices
     */
    private function sendPushToAndroid($device, $title, $data)
    {
        $url = 'https://android.googleapis.com/gcm/send';
        $apiKey = "AIzaSyCV8JMQ000zMSbA5GRFUBpHg6FFhwG6Rg8";
        $headers = array('Authorization: key=' . $apiKey, 'Content-Type: application/json');
        $fields = array('registration_ids' => $device, 'data' => ["title" => $title, "data" => $data]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));

        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }

    /**
     * Send Push to iOS devices
     */
    private function sendPushToIOS($device, $title, $data)
    {
        if (true) {
            // For Dev
            $apnsHost = 'gateway.sandbox.push.apple.com';
            $apnsCert = getcwd().'/../config/apns_dist.pem';
//            $apnsCert = getcwd() . '/../config/apns_dev.pem';
        } else {
            // For Service
            $apnsHost = 'gateway.push.apple.com';
            $apnsCert = getcwd().'/../config/apns_dist.pem';
        }
        $apnsPort = 2195;

        $payload = [
            'aps' => [
                'alert' => $title,
                'badge' => "0",
                'sound' => 'default',
                'data' => $data
            ]
        ];
        $payload = json_encode($payload);
        $streamContext = stream_context_create();
        stream_context_set_option($streamContext, 'ssl', 'local_cert', $apnsCert);
        $apns = stream_socket_client('ssl://' . $apnsHost . ':' . $apnsPort, $error, $errorString, 100, STREAM_CLIENT_CONNECT, $streamContext);
        if ($apns) {
            $apnsMessage = chr(0) . chr(0) . chr(32) . @pack('H*', str_replace(' ', '', $device)) . chr(0) . chr(strlen($payload)) . $payload;
            fwrite($apns, $apnsMessage);
            fclose($apns);
            return true;
        }

        return false;
    }

    public function sendPushAnswer($oppo_user_id, $chat_id, $user_id, $answer)
    {
        $this->sendPushMessage($oppo_user_id, 'New Chat Created.', [
            'type' => 'chatcreated',
            'chat_id' => $chat_id,
            'user_id' => $user_id,
            'answer' => $answer,
        ]);
    }

    public function sendPushStartBlindChat($oppo_user_id, $chat_id)
    {
        $this->sendPushMessage($oppo_user_id, 'User Started.', [
            'type' => 'userstart',
            'chat_id' => $chat_id,
        ]);
    }

    public function sendPushChatMessageSent($oppo_user_id, $chat_id, $message_id, $message, $user_name, $user_img)
    {
        $this->sendPushMessage($oppo_user_id, 'Message Sent.', [
            'type' => 'messagesent',
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'message' => $message,
            'user_name' => $user_name,
            'user_img' => $user_img,
        ]);
    }

    public function sendPushChatMessageReceived($oppo_user_id, $chat_id, $message_id)
    {
        $this->sendPushMessage($oppo_user_id, 'Message Received.', [
            'type' => 'messagereceived',
            'chat_id' => $chat_id,
            'message_id' => $message_id
        ]);
    }

    public function sendPushConnect($oppo_user_id, $chat_id)
    {
        $this->sendPushMessage($oppo_user_id, 'User Connected.', [
            'type' => 'userconnect',
            'chat_id' => $chat_id,
        ]);
    }

    public function sendPushRequestDisconnect($oppo_user_id, $chat_id, $message, $is_blind_chat)
    {
        $this->sendPushMessage($oppo_user_id, 'User Wants Disconnect.', [
            'type' => 'requestdisconnect',
            'chat_id' => $chat_id,
            'message' => $message,
            'is_blind_chat' => $is_blind_chat
        ]);
    }

    public function sendPushLastChance($oppo_user_id, $chat_id, $message, $is_blind_chat, $user_name, $user_img, $user_gender)
    {
        $this->sendPushMessage($oppo_user_id, 'Last Chance Sent.', [
            'type' => 'lastchancesend',
            'chat_id' => $chat_id,
            'message' => $message,
            'is_blind_chat' => $is_blind_chat,
            'user_name' => $user_name,
            'user_img' => $user_img,
            'user_gender' => $user_gender
        ]);
    }

    public function sendPushConfirmDisconnect($oppo_user_id, $chat_id, $message, $user_name, $is_blind_chat)
    {
        $this->sendPushMessage($oppo_user_id, 'User Confirmed Disconnect.', [
            'type' => 'confirmdisconnect',
            'chat_id' => $chat_id,
            'message' => $message,
            'user_name' => $user_name,
            'is_blind_chat' => $is_blind_chat
        ]);
    }

    public function sendPushReconnect($oppo_user_id, $chat_id, $is_blind_chat)
    {
        $this->sendPushMessage($oppo_user_id, 'Chat Reconnected.', [
            'type' => 'chatreconnect',
            'chat_id' => $chat_id,
            'is_blind_chat' => $is_blind_chat,
        ]);
    }

    public function sendPushOnlineState($oppo_user_id, $chat_id, $user_id, $online_state)
    {
        $this->sendPushMessage($oppo_user_id, 'Online State Sent.', [
            'type' => 'onlinestate',
            'chat_id' => $chat_id,
            'user_id' => $user_id,
            'online_state' => $online_state,
        ]);
    }
}
