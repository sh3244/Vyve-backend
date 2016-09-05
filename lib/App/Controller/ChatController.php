<?php
/**
 * User: Sasaki Kenski
 * Date: 2016-03-03
 */

namespace App\Controller;

use App\Config;

class ChatController extends BaseController
{
    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
    }

    private static function geoDistance($lat1, $lon1, $lat2, $lon2, $unit="m")
    {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;

        $unit = strtolower($unit);
        if ($unit == "k") {
            return ($miles * 1.609344);
        } else {
            return $miles;
        }
    }

    private function matchStartBlindDate($user_id, $oppo_user_id)
    {
        $min_user = min($user_id, $oppo_user_id);
        $max_user = max($user_id, $oppo_user_id);
        $user_multiple = $min_user . "*" . $max_user;
        $stmt = parent::database()->prepare("SELECT * FROM `tbl_chat_list` WHERE user_multiple = '$user_multiple'");
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($result != null)
            return false;

        $stmt = parent::database()->prepare("SELECT * FROM `tbl_user` WHERE id = $user_id");
        $stmt->execute();
        $result_me = $stmt->fetch(\PDO::FETCH_ASSOC);


        $stmt = parent::database()->prepare("SELECT * FROM `tbl_user` WHERE id = $oppo_user_id");
        $stmt->execute();
        $result_oppo = $stmt->fetch(\PDO::FETCH_ASSOC);


        if ($result_me['age'] > $result_oppo['setting_age_max'] || $result_me['age'] < $result_oppo['setting_age_min']) {
//            echo "setting my age is unmatch,".$result_me['age']."------".$result_oppo['setting_age_min']."------".$result_oppo['setting_age_max']."-------------------------";
            return false;
        }

        if ($result_oppo['age'] > $result_me['setting_age_max'] || $result_oppo['age'] < $result_me['setting_age_min']) {
//            echo "setting oppo age is unmatch,".$result_oppo['age']."------".$result_me['setting_age_min']."------".$result_me['setting_age_max']."-------------------------";
            return false;
        }

        if (($result_oppo['setting_gender'] != 2) && ($result_me['gender'] != $result_oppo['setting_gender'])) {
//            echo "setting oppo gender is unmatch,".$result_oppo['setting_gender']."------".$result_me['gender']."-------------------------";
            return false;
        }

        if (($result_me['setting_gender'] != 2) && ($result_oppo['gender'] != $result_me['setting_gender'])) {
//            echo "setting my gender is unmatch,".$result_me['setting_gender']."------".$result_oppo['gender']."-------------------------";
            return false;
        }

        $distance = self::geoDistance($result_oppo['latitude'],  $result_oppo['longitude'], $result_me['latitude'], $result_me['longitude']);
        if ($distance > $result_me['setting_distance'] || $distance > $result_oppo['setting_distance']) {
//            echo "setting distance is unmatch,".$distance."======".$result_me['setting_distance']."------".$result_oppo['setting_distance']."-------------------------";
            return false;
        }

        return true;
    }

    private function isValidUser($user_id)
    {
        $stmt = parent::database()->prepare("SELECT * FROM `tbl_user` WHERE id = :user_id");
        $stmt->bindParam("user_id", $user_id);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($result == null)
            return false;
        else
            return true;
    }

    private function isValidQuestion($question_id)
    {
        $stmt = parent::database()->prepare("SELECT * FROM `tbl_question` WHERE id = :question_id");
        $stmt->bindParam("question_id", $question_id);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($result == null)
            return false;
        else
            return true;
    }

    private function isValidBlindChat($chat_id)
    {
        $stmt = parent::database()->prepare("SELECT * FROM `tbl_blind_chat` WHERE id = :chat_id");
        $stmt->bindParam("chat_id", $chat_id);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($result == null)
            return false;
        else
            return true;
    }

    private function isValidChatRoom($chat_id)
    {
        $stmt = parent::database()->prepare("SELECT * FROM `tbl_chat_list` WHERE id = :chat_id");
        $stmt->bindParam("chat_id", $chat_id);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($result == null)
            return false;
        else
            return true;
    }

    private function getRandomQuestion() {
        $selectSql = "SELECT id, question FROM tbl_question ORDER BY RAND() LIMIT 1";
        $stmt = parent::database()->prepare($selectSql);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $json_result = [
            'question_id' => $result['id'],
            'question' => $result['question'],
        ];

        return $json_result;
    }

    private function startNewBlindChat($user_id, $question_id, $answer)
    {
        try {
            parent::database()->beginTransaction();
            $insertSql = "INSERT INTO `tbl_blind_chat`
                                (`user_id`,
                                `question_id`,
                                `first_answer`,
                                `create_date`)
                                VALUES
                                (:user_id,
                                :question_id,
                                :first_answer,
                                now())";
            $stmt = parent::database()->prepare($insertSql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->bindParam("question_id", $question_id);
            $stmt->bindParam("first_answer", $answer);
            $stmt->execute();
            $result = parent::database()->lastInsertId();
            parent::database()->commit();
        } catch (\PDOException $e) {
            parent::database()->rollBack();

           return -1;
        }

        return $result;
    }

    private static $CHAT_ROOM_NORMAL = 0;
    private static $CHAT_ROOM_REQUEST_DISCONNECT = 1;
    private static $CHAT_ROOM_LAST_CHANCE = 2;
    private static $CHAT_ROOM_RECONNECTED = 3;
    private static $CHAT_ROOM_CONFIRM_DISCONNECT = 4;

    private function isAcceptableState($chat_id, $messageStatus, $answer)
    {
        $selecteSql = "SELECT room_status FROM tbl_chat_list where `id` = :chat_id";
        $stmt = parent::database()->prepare($selecteSql);
        $stmt->bindParam("chat_id", $chat_id);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $currentRoomStatus = $result['room_status'];

        switch ($currentRoomStatus) {
            case self::$CHAT_ROOM_NORMAL:
            case self::$CHAT_ROOM_RECONNECTED:
                if ($messageStatus == self::$CHAT_ROOM_NORMAL || $messageStatus == self::$CHAT_ROOM_REQUEST_DISCONNECT)
                    return true;
                break;
            case self::$CHAT_ROOM_REQUEST_DISCONNECT:
                if ($messageStatus == self::$CHAT_ROOM_LAST_CHANCE || $messageStatus == self::$CHAT_ROOM_CONFIRM_DISCONNECT)
                    return true;
                break;
            case self::$CHAT_ROOM_LAST_CHANCE:
                if ($messageStatus == self::$CHAT_ROOM_CONFIRM_DISCONNECT || $messageStatus == self::$CHAT_ROOM_RECONNECTED)
                    return true;
                break;
        }

        return false;
    }

    private function insertNewChatContent($chat_id, $user_id, $answer, $message_status = 0)
    {
        if (!self::isAcceptableState($chat_id, $message_status, $answer)) {
            return null;
        }

        $message_id = 0;
        try {
            parent::database()->beginTransaction();
            $insertSql = "INSERT INTO `tbl_chat_content`
                                            (`chat_id`,
                                            `user_id`,
                                            `message`,
                                            `message_status`)
                                            VALUES
                                            (:chat_id,
                                            :user_id,
                                            :message,
                                            :message_status)";
            $stmt = parent::database()->prepare($insertSql);
            $stmt->bindParam("chat_id", $chat_id);
            $stmt->bindParam("user_id", $user_id);
            $stmt->bindParam("message", $answer);
            $stmt->bindParam("message_status", $message_status);
            $stmt->execute();
            $message_id = parent::database()->lastInsertId();
            parent::database()->commit();
        } catch (\PDOException $e) {
            parent::database()->rollBack();
        }

        try {
            parent::database()->beginTransaction();
            $updateSql = "UPDATE `tbl_chat_list`
                            SET
                            `last_chat_time` = now(),
                            `last_chat_user_id` = :last_chat_user_id,
                            `last_chat_message` = :last_chat_message,
                            `room_status` = :room_status,
                            `first_message_count` = IF (first_user_id = :last_chat_user_id,  first_message_count + 1, first_message_count),
                            `second_message_count` = IF (second_user_id = :last_chat_user_id,  second_message_count + 1, second_message_count)
                            WHERE `id` = :chat_id";
            $stmt = parent::database()->prepare($updateSql);
            $stmt->bindParam("last_chat_user_id", $user_id);
            $stmt->bindParam("last_chat_message", $answer);
            $stmt->bindParam("room_status", $message_status);
            $stmt->bindParam("chat_id", $chat_id);
            $stmt->execute();
            parent::database()->commit();
        } catch (\PDOException $e) {
            parent::database()->rollBack();
        }

        if ($message_status == self::$CHAT_ROOM_REQUEST_DISCONNECT) {
            try {
                $future = new \DateTime("now +12 hours");

                parent::database()->beginTransaction();
                $updateSql = "UPDATE `tbl_chat_list`
                            SET
                            `first_disconnect_request_user` = :user_id,
                            `terminate_time` = :future
                            WHERE `id` = :chat_id";
                $stmt = parent::database()->prepare($updateSql);
                $stmt->bindParam("user_id", $user_id);
                $stmt->bindParam("chat_id", $chat_id);
                $stmt->bindParam("future", $future->format('Y-m-d H:i:s'));
                $stmt->execute();
                parent::database()->commit();
            } catch (\PDOException $e) {
                parent::database()->rollBack();
            }
        }

        if ($message_status == self::$CHAT_ROOM_CONFIRM_DISCONNECT) {
            try {
                parent::database()->beginTransaction();
                $updateSql = "UPDATE `tbl_chat_list`
                            SET
                            `first_user_deleted` = IF (first_user_id = :user_id,  1, `first_user_deleted`),
                            `second_user_deleted` = IF (second_user_id = :user_id,  1, `second_user_deleted`)
                            WHERE `id` = :chat_id";
                $stmt = parent::database()->prepare($updateSql);
                $stmt->bindParam("user_id", $user_id);
                $stmt->bindParam("chat_id", $chat_id);
                $stmt->execute();
                parent::database()->commit();
            } catch (\PDOException $e) {
                parent::database()->rollBack();
            }
        }

        return $message_id;
    }

    private function getOppoUserId($user_id, $chat_id)
    {
        $selecteSql = "SELECT IF (first_user_id = :user_id,  second_user_id, first_user_id) as oppo_user_id FROM tbl_chat_list where `id` = :chat_id";
        $stmt = parent::database()->prepare($selecteSql);
        $stmt->bindParam("user_id", $user_id);
        $stmt->bindParam("chat_id", $chat_id);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result['oppo_user_id'];
    }

    private function calculateFriendShip($firstMessageCount, $secondMessageCount)
    {
        $messageCountLevel = 50;
        if ($firstMessageCount < $messageCountLevel || $secondMessageCount < $messageCountLevel)
            return 0;

        if ($firstMessageCount < $messageCountLevel * 2 || $secondMessageCount < $messageCountLevel * 2)
            return 1;

        if ($firstMessageCount < $messageCountLevel * 3 || $secondMessageCount < $messageCountLevel * 3)
            return 2;

        if ($firstMessageCount < $messageCountLevel * 4 || $secondMessageCount < $messageCountLevel * 4)
            return 3;

        return 4;
    }

    private function getBlindDateCount($user_id) {
        $blind_count = 0;
        try {
            $selectSql = "select count(*) as count_blind
                          from tbl_blind_chat
                          where user_id = :user_id";
            $stmt = parent::database()->prepare($selectSql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            $blind_count += $result['count_blind'];
        } catch (\PDOException $e) {
        }

        try {
            // room state is normal or reconnect.
            // or user that request disconnect first is not me.
            $selectSql = "select count(*) as count_blind
                          from tbl_chat_list
                          where (room_status = 0 or room_status = 3 or first_disconnect_request_user != :user_id) and
                          (first_user_matched & second_user_matched) = 0 and
                          (first_user_id = :user_id || second_user_id = :user_id)";
            $stmt = parent::database()->prepare($selectSql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            $blind_count += $result['count_blind'];
        } catch (\PDOException $e) {
        }

        return $blind_count;
    }

    private function getMatchListCount($user_id) {
        $match_count = 0;

        try {
            // room state is normal or reconnect.
            $selectSql = "select count(*) as count_blind
                          from tbl_chat_list
                          where (room_status = 0 or room_status = 3) and
                          (first_user_matched & second_user_matched) = 1 and
                          (first_user_id = :user_id || second_user_id = :user_id)";
            $stmt = parent::database()->prepare($selectSql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            $match_count += $result['count_blind'];
        } catch (\PDOException $e) {
        }

        return $match_count;
    }

    private function isBlindLimited($user_id) {
        try {
            $selectSql = "select is_blind_unlimited
                          from tbl_user
                          where id = :user_id";
            $stmt = parent::database()->prepare($selectSql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result['is_blind_unlimited'])
                return false;

            $now = date("Y-m-d");
            $selectSql = "select last_blind_count
                          from tbl_user
                          where id = :user_id and last_blind_date = :now";
            $stmt = parent::database()->prepare($selectSql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->bindParam("now", $now);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result['last_blind_count'] <= 5)
                return false;
        } catch (\PDOException $e) {
        }

        return true;
    }

    private function isMatchedChat($chat_id) {
        try {
            $selectSql = "select *
                          from tbl_chat_list
                          where id = :chat_id";
            $stmt = parent::database()->prepare($selectSql);
            $stmt->bindParam("chat_id", $chat_id);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return ($result['first_user_matched'] && $result['second_user_matched']);
        } catch (\PDOException $e) {
            return false;
        }
    }

    private function addToQuestionHistory($user_id, $question_id, $answer) {

        try {
            parent::database()->beginTransaction();
            $insertSql = "INSERT INTO `tbl_question_history`
                                (`user_id`,
                                `question_id`,
                                `answer`,
                                `created_at`)
                                VALUES
                                (:user_id,
                                :question_id,
                                :answer,
                                now())";
            $stmt = parent::database()->prepare($insertSql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->bindParam("question_id", $question_id);
            $stmt->bindParam("answer", $answer);
            $stmt->execute();
            parent::database()->commit();
        } catch (\PDOException $e) {
            parent::database()->rollBack();
        }

        self::resetBlindLimited($user_id);
    }

    private function resetBlindLimited($user_id) {
        $now = date("Y-m-d");

        try {
            $selectSql = "select last_blind_date
                          from tbl_user
                          where id = :user_id";
            $stmt = parent::database()->prepare($selectSql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result['last_blind_date'] == $now) {
                parent::database()->beginTransaction();
                $updateSql = "UPDATE tbl_user set last_blind_count = last_blind_count + 1
                          where id = :user_id and last_blind_date = :now";
                $stmt = parent::database()->prepare($updateSql);
                $stmt->bindParam("user_id", $user_id);
                $stmt->bindParam("now", $now);
                $stmt->execute();
                parent::database()->commit();
            } else {
                parent::database()->beginTransaction();
                $updateSql = "UPDATE tbl_user set last_blind_date = :now, last_blind_count = 1
                          where id = :user_id";
                $stmt = parent::database()->prepare($updateSql);
                $stmt->bindParam("user_id", $user_id);
                $stmt->bindParam("now", $now);
                $stmt->execute();
                parent::database()->commit();
            }
        } catch (\PDOException $e) {
            parent::database()->rollBack();
        }
    }

    public function showQuestionLog()
    {
        $user_id = parent::param('user_id'); //user id

        try {
            if (!self::isValidUser($user_id)) {
                return parent::abort(400, "Request from invalid user.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        try {
            $selectSql = "select h.id as history_id, q.id as question_id, q.question as question, h.answer as answer, h.created_at as create_time
                          from tbl_question_history h
                          left join tbl_question q on h.question_id = q.id
                          where h.user_id = :user_id";
            $stmt = parent::database()->prepare($selectSql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->execute();
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return parent::response($result);
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }
    }

    public function startBlindDate($token)
    {
        $user_id = parent::param('user_id'); //user id

        /* Check if token has needed scope. */
        if ($user_id != $token->getUserId()) {
            return parent::abort(403, "Unauthorized behavior");
        }

        try {
            if (!self::isValidUser($user_id)) {
                return parent::abort(400, "Request from invalid user.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        if (self::getBlindDateCount($user_id) >= 3) {
            return parent::abort(401, "Blind chat count exceed 3.");
        }

        if (self::isBlindLimited($user_id))
            return parent::abort(403, "Blind date created over 5 per day.");

        try {
            $selectSql = "select b.id as b_id, b.user_id as b_user_id, q.id as q_id, q.question as q_question
                          from tbl_blind_chat b
                          left join tbl_question q on b.question_id = q.id
                          where user_id != :user_id";
            $stmt = parent::database()->prepare($selectSql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->execute();
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if ($result == null) {
                return parent::response(self::getRandomQuestion());
            } else {
                for ($i = 0; $i < count($result); $i++) {
                    $oppo_user_id = $result[$i]["b_user_id"];
                    if (self::matchStartBlindDate($user_id, $oppo_user_id)) { //if match the conversation condition
                        $json_result = [
                            'question_id' => $result[$i]["q_id"],
                            'question' => $result[$i]["q_question"],
                        ];

                        return parent::response($json_result);
                    }
                }
                // else
                return parent::response(self::getRandomQuestion());
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }
    }

    public function answerBlindDate()
    {
        $user_id = parent::param('user_id'); //user id
        $question_id = parent::param('question_id'); //question id
        $answer = parent::param('answer'); //answer

        try {
            if (!self::isValidUser($user_id)) {
                return parent::abort(400, "Request from invalid user.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        try {
            if (!self::isValidQuestion($question_id)) {
                return parent::abort(400, "Invalid question.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        self::addToQuestionHistory($user_id, $question_id, $answer);

        try {
            $selectSql = "Select * from tbl_blind_chat where user_id != :user_id and question_id = :question_id";
            $stmt = parent::database()->prepare($selectSql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->bindParam("question_id", $question_id);
            $stmt->execute();
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if ($result == null) {
                if (self::startNewBlindChat($user_id, $question_id, $answer) == -1) {
                    return parent::abort(300, "DB insertion error.");
                }

                $json_result = [
                    'is_blindchat_stated' => false,
                    'chatroom_id' => 0,
                ];
                return parent::response($json_result);
            } else {
                for ($i = 0; $i < count($result); $i++) {
                    $oppo_user_id = $result[$i]["user_id"];
                    if (self::matchStartBlindDate($user_id, $oppo_user_id)) { //if match the conversation condition
                        $blindChatId = $result[$i]["id"];
                        $first_answer = $result[$i]["first_answer"];
                        $first_answer_time = $result[$i]["create_date"];
                        try {
                            parent::database()->beginTransaction();
                            $deleteSql = "DELETE from `tbl_blind_chat` WHERE `id` = :blind_chat_id";
                            $stmt = parent::database()->prepare($deleteSql);
                            $stmt->bindParam("blind_chat_id", $blindChatId);
                            $stmt->execute();
                            parent::database()->commit();
                        } catch (\PDOException $e) {
                            parent::database()->rollBack();
                            return parent::abort(300, $e->getMessage());
                        }

                        // create chat room.
                        try {
                            parent::database()->beginTransaction();
                            $insertSql = "INSERT INTO `tbl_chat_list`
                                        (`first_user_id`,
                                        `second_user_id`,
                                        `user_multiple`,
                                        `question_id`,
                                        `first_user_answer`,
                                        `second_user_answer`,
                                        `create_date`)
                                        VALUES
                                        (:first_user_id,
                                        :second_user_id,
                                        :user_multiple,
                                        :question_id,
                                        :first_user_answer,
                                        :second_user_answer,
                                        now())";
                            $stmt = parent::database()->prepare($insertSql);
                            $stmt->bindParam("first_user_id", $oppo_user_id);
                            $stmt->bindParam("second_user_id", $user_id);
                            $min_user = min($oppo_user_id, $user_id);
                            $max_user = max($oppo_user_id, $user_id);
                            $user_multiple = $min_user . "*" . $max_user;
                            $stmt->bindParam("user_multiple", $user_multiple);
                            $stmt->bindParam("question_id", $question_id);
                            $stmt->bindParam("first_user_answer", $first_answer);
                            $stmt->bindParam("second_user_answer", $answer);
                            $stmt->execute();
                            parent::database()->commit();

                            $stmt = parent::database()->prepare("SELECT id FROM `tbl_chat_list` WHERE first_user_id = :first_user_id AND second_user_id = :second_user_id");
                            $stmt->bindParam("first_user_id", $oppo_user_id);
                            $stmt->bindParam("second_user_id", $user_id);
                            $stmt->execute();
                            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                            $chat_id = $result["id"];
                        } catch (\PDOException $e) {
                            parent::database()->rollBack();
                            return parent::abort(300, $e->getMessage());
                        }

                        // PUSH MUST CHECK
                        $push = new PushController();
                        $push->sendPushAnswer($user_id, $chat_id, $oppo_user_id, $first_answer);
                        $push->sendPushAnswer($oppo_user_id, $chat_id, $user_id, $answer);

                        $json_result = [
                            'is_blindchat_stated' => true,
                            'chatroom_id' => $chat_id,
                        ];
                        return parent::response($json_result);
                    }
                }

                // Else
                if (self::startNewBlindChat($user_id, $question_id, $answer) == -1) {
                    return parent::abort(300, "DB insertion error.");
                }

                $json_result = [
                    'is_blindchat_stated' => false,
                    'chatroom_id' => 0,
                ];
                return parent::response($json_result);
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }
    }

    public function startBlindChat()
    {
        $user_id = parent::param('user_id'); //user id
        $chat_id = parent::param('chat_id'); //chat room id

        try {
            if (!self::isValidUser($user_id)) {
                return parent::abort(400, "Request from invalid user.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        try {
            if (!self::isValidChatRoom($chat_id)) {
                return parent::abort(400, "Invalid chat room.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        try {
            parent::database()->beginTransaction();
            $updateSql = "UPDATE `tbl_chat_list`
                            SET
                            `first_user_start` = IF (first_user_id = :user_id,  1, `first_user_start`),
                            `second_user_start` = IF (second_user_id = :user_id,  1, `second_user_start`)
                            WHERE `id` = :chat_id";
            $stmt = parent::database()->prepare($updateSql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->bindParam("chat_id", $chat_id);
            $stmt->execute();
            parent::database()->commit();
        } catch (\PDOException $e) {
            parent::database()->rollBack();
            return parent::abort(300, $e->getMessage());
        }

        try {
            $oppo_user_id = $this->getOppoUserId($user_id, $chat_id);

            // PUSH MUST CHECK
            $push = new PushController();
            $push->sendPushStartBlindChat($oppo_user_id, $chat_id);
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        parent::response("success");
    }

    public function sendMessageInChat()
    {
        $user_id = parent::param('user_id'); //user id
        $chat_id = parent::param('chat_id'); //chat room id
        $message = parent::param('message'); //message
        $created_at = parent::param('created_at'); //message

        try {
            if (!self::isValidUser($user_id)) {
                return parent::abort(400, "Request from invalid user.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        try {
            if (!self::isValidChatRoom($chat_id)) {
                return parent::abort(400, "Invalid chat room.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        try {
            $message_id = $this->insertNewChatContent($chat_id, $user_id, $message);
            if (!$message_id)
                return parent::abort(400, "Invalid Room Status Request.");

            $oppo_user_id = self::getOppoUserId($user_id, $chat_id);
            $selectSql = "SELECT photo1, firstname, lastname FROM `tbl_user` WHERE id = :user_id";
            $stmt = parent::database()->prepare($selectSql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            // PUSH MUST CHECK
            $push = new PushController();
            $push->sendPushChatMessageSent($oppo_user_id, $chat_id, $message_id, $message,
                $result["firstname"], Config::host()['resource'].$result["photo1"]);
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        parent::response(["message_id" => $message_id, "created_at" => $created_at]);
    }

    public function sendMessageReceived()
    {
        $user_id = parent::param('user_id'); //user id
        $chat_id = parent::param('chat_id'); //chat room id
        $message_id = parent::param('message_id'); //message id

        try {
            if (!self::isValidUser($user_id)) {
                return parent::abort(400, "Request from invalid user.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        try {
            if (!self::isValidChatRoom($chat_id)) {
                return parent::abort(400, "Invalid chat room.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        try {
            parent::database()->beginTransaction();
            $insertSql = "UPDATE `tbl_chat_content` set `receive_check` = 1 where id = :message_id";
            $stmt = parent::database()->prepare($insertSql);
            $stmt->bindParam("message_id", $message_id);
            $stmt->execute();
            parent::database()->commit();
        } catch (\PDOException $e) {
            parent::database()->rollBack();
        }

        $oppo_user_id = self::getOppoUserId($user_id, $chat_id);
        // PUSH MUST CHECK
        $push = new PushController();
        $push->sendPushChatMessageReceived($oppo_user_id, $chat_id, $message_id);

        parent::response("success");
    }

    public function getBlindList()
    {
        $user_id = parent::param('user_id'); //user id

        try {
            if (!self::isValidUser($user_id)) {
                return parent::abort(400, "Request from invalid user.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        $return_array = array();
        try {
            $selectSql = "select b.id as b_id, b.create_date as b_create_date, b.first_answer as b_first_answer, q.id as q_id, q.question as q_question
                        from tbl_blind_chat b
                        left join tbl_question q on b.question_id = q.id
                        where b.user_id = :user_id";
            $stmt = parent::database()->prepare($selectSql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->execute();
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            for ($i = 0; $i < count($result); $i++) {
                $row_array['is_chat_begin'] = 0;
                $row_array['question_id'] = $result[$i]['q_id'];
                $row_array['question'] = $result[$i]['q_question'];
                $row_array['chat_id'] = $result[$i]['b_id'];
                $row_array['oppo_user_id'] = 0;
                $row_array['oppo_user_name'] = 'ICE BREAKER';
                $row_array['oppo_user_online'] = false;
                $row_array['last_time'] = $result[$i]['b_create_date'];
                $row_array['last_message'] = $result[$i]['b_first_answer'];
                $row_array['room_status'] = self::$CHAT_ROOM_NORMAL;

                array_push($return_array, $row_array);
            }

            // room state is normal or reconnect.
            // or user that request disconnect first is not me.
            // (room_status = 0 or room_status = 3 or first_disconnect_request_user != :user_id)
            // this part should be checked in client
            $selectSql = "select c.id as c_id, c.last_chat_time as c_last_chat_time, c.last_chat_message as c_last_chat_message, c.room_status as c_room_status,
                          q.id as q_id, q.question as q_question, c.first_disconnect_request_user as first_disconnect_request_user,
                          c.first_user_start as c_first_user_start, c.second_user_start as c_second_user_start,
                          c.first_user_id as c_first_user_id, f.firstname as f_firstname, f.is_online as f_is_online, f.gender as f_gender, f.photo1 as f_photo1,
                          c.second_user_id as c_second_user_id, s.firstname as s_firstname, s.is_online as s_is_online, s.gender as s_gender, s.photo1 as s_photo1
                          from tbl_chat_list c
                          left join tbl_user f on f.id = c.first_user_id
                          left join tbl_user s on s.id = c.second_user_id
                          left join tbl_question q on q.id = c.question_id
                          where
                          ((first_user_id = :user_id and first_user_deleted = 0) or (second_user_id = :user_id and second_user_deleted = 0)) and
                          (first_user_matched & second_user_matched) = 0";
            $stmt = parent::database()->prepare($selectSql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->execute();
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            for ($i = 0; $i < count($result); $i++) {
                $row_array['is_chat_begin'] = 1;
                $row_array['question_id'] = $result[$i]['q_id'];
                $row_array['question'] = $result[$i]['q_question'];
                $row_array['chat_id'] = $result[$i]['c_id'];
                if ($result[$i]['c_first_user_id'] == $user_id) {
                    $row_array['my_chat_start'] = $result[$i]['c_first_user_start'];
                    $row_array['oppo_chat_start'] = $result[$i]['c_second_user_start'];
                    $row_array['oppo_user_id'] = $result[$i]['c_second_user_id'];
                    $row_array['oppo_user_name'] = $result[$i]['s_firstname'];
                    $row_array['oppo_user_online'] = $result[$i]['s_is_online'];
                    $row_array['oppo_user_gender'] = $result[$i]['s_gender'];
                    $row_array['image_url'] = Config::host()['resource'].$result[$i]['s_photo1'];
                } else {
                    $row_array['my_chat_start'] = $result[$i]['c_second_user_start'];
                    $row_array['oppo_chat_start'] = $result[$i]['c_first_user_start'];
                    $row_array['oppo_user_id'] = $result[$i]['c_first_user_id'];
                    $row_array['oppo_user_name'] = $result[$i]['f_firstname'];
                    $row_array['oppo_user_online'] = $result[$i]['f_is_online'];
                    $row_array['oppo_user_gender'] = $result[$i]['f_gender'];
                    $row_array['image_url'] = Config::host()['resource'].$result[$i]['f_photo1'];
                }
                $row_array['last_time'] = $result[$i]['c_last_chat_time'];
                $row_array['last_message'] = $result[$i]['c_last_chat_message'];
                $row_array['room_status'] = $result[$i]['c_room_status'];
                $row_array['first_disconnect_request_user'] = $result[$i]['first_disconnect_request_user'];

                array_push($return_array, $row_array);
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        return parent::response($return_array);
    }

    public function getMatchList()
    {
        $user_id = parent::param('user_id'); //user id

        try {
            if (!self::isValidUser($user_id)) {
                return parent::abort(400, "Request from invalid user.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        $return_array = array();
        try {
            $selectSql = "select c.id as c_id, c.last_chat_user_id as c_last_chat_user_id, c.last_chat_time as c_last_chat_time, c.last_chat_message as c_last_chat_message,
                          c.room_status as c_room_status, c.first_disconnect_request_user as first_disconnect_request_user,
                          c.first_message_count as c_first_message_count, c.second_message_count as c_second_message_count,
                          c.first_user_id as c_first_user_id, f.firstname as f_firstname, f.is_online as f_is_online, f.photo1 as f_photo1,
                          c.second_user_id as c_second_user_id, s.firstname as s_firstname, s.is_online as s_is_online, s.photo1 as s_photo1
                          from tbl_chat_list c
                          left join tbl_user f on f.id = c.first_user_id
                          left join tbl_user s on s.id = c.second_user_id
                          where ((first_user_id = :user_id and first_user_deleted = 0) or (second_user_id = :user_id and second_user_deleted = 0)) and
                          (first_user_matched & second_user_matched) = 1";
            $stmt = parent::database()->prepare($selectSql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->execute();
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            for ($i = 0; $i < count($result); $i++) {
                $row_array['chat_id'] = $result[$i]['c_id'];
                if ($result[$i]['c_first_user_id'] == $user_id) {
                    $row_array['oppo_user_id'] = $result[$i]['c_second_user_id'];
                    $row_array['oppo_user_name'] = $result[$i]['s_firstname'];
                    $row_array['oppo_user_online'] = $result[$i]['s_is_online'];
                    $row_array['image_url'] = Config::host()['resource'].$result[$i]['s_photo1'];
                } else {
                    $row_array['oppo_user_id'] = $result[$i]['c_first_user_id'];
                    $row_array['oppo_user_name'] = $result[$i]['f_firstname'];
                    $row_array['oppo_user_online'] = $result[$i]['f_is_online'];
                    $row_array['image_url'] = Config::host()['resource'].$result[$i]['f_photo1'];
                }
                if ($result[$i]['c_last_chat_user_id'] == $user_id)
                    $row_array['is_last_message_writer'] = true;
                else
                    $row_array['is_last_message_writer'] = false;
                $row_array['last_message_time'] = $result[$i]['c_last_chat_time'];
                $row_array['last_message'] = $result[$i]['c_last_chat_message'];
                $row_array['room_status'] = $result[$i]['c_room_status'];
                $row_array['first_disconnect_request_user'] = $result[$i]['first_disconnect_request_user'];
                $row_array['friendship'] = self::calculateFriendShip($result[$i]['c_first_message_count'], $result[$i]['c_second_message_count']);

                array_push($return_array, $row_array);
            }

        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        return parent::response($return_array);
    }

    public function getChatContent()
    {
        $user_id = parent::param('user_id'); //user id
        $is_chat_begin = parent::param('is_chat_begin'); //check blind chat status.
        $chat_id = parent::param('chat_id'); //chat room id

        try {
            if (!self::isValidUser($user_id)) {
                return parent::abort(400, "Request from invalid user.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        try {
            if ($is_chat_begin && !self::isValidChatRoom($chat_id)) {
                return parent::abort(400, "Invalid chat room.");
            }

            if (!$is_chat_begin && !self::isValidBlindChat($chat_id)) {
                return parent::abort(400, "Invalid chat room.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        if ($is_chat_begin) {
            try {
                $selectSql = "select c.id as c_id, c.last_chat_time as c_last_chat_time, c.last_chat_message as c_last_chat_message, c.room_status as c_room_status, c.create_date as c_create_date, c.terminate_time as c_terminate_time,
                          c.first_message_count as c_first_message_count, c.second_message_count as c_second_message_count,
                          c.first_user_answer as c_first_user_answer, c.second_user_answer as c_second_user_answer,
                          c.first_user_start as c_first_user_start, c.second_user_start as c_second_user_start,
                          c.first_user_matched as c_first_user_matched, c.second_user_matched as c_second_user_matched,
                          c.first_user_id as c_first_user_id, f.firstname as f_firstname, f.photo1 as f_photo1, c.second_user_id as c_second_user_id, s.firstname as s_firstname, s.photo1 as s_photo1,
                          c.question_id as c_question_id, q.question as q_question
                          from tbl_chat_list c
                          left join tbl_user f on f.id = c.first_user_id
                          left join tbl_user s on s.id = c.second_user_id
                          left join tbl_question q on q.id = c.question_id
                          where c.id = :chat_id";
                $stmt = parent::database()->prepare($selectSql);
                $stmt->bindParam("chat_id", $chat_id);
                $stmt->execute();
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);

                $row_array['is_chat_begin'] = 1;
                $row_array['chat_id'] = $result['c_id'];
                $row_array['question_id'] = $result['c_question_id'];
                $row_array['question'] = $result['q_question'];
                if ($result['c_first_user_id'] == $user_id) {
                    $row_array['my_chat_start'] = $result['c_first_user_start'];
                    $row_array['oppo_chat_start'] = $result['c_second_user_start'];
                    $row_array['my_chat_matched'] = $result['c_first_user_matched'];
                    $row_array['oppo_chat_matched'] = $result['c_second_user_matched'];
                    $row_array['oppo_user_id'] = $result['c_second_user_id'];
                    $row_array['oppo_user_name'] = $result['s_firstname'];
                    $row_array['my_message_count'] = $result['c_first_message_count'];
                    $row_array['other_message_count'] = $result['c_second_message_count'];
                    $row_array['my_answer'] = $result['c_first_user_answer'];
                    $row_array['other_answer'] = $result['c_second_user_answer'];
                    $row_array['image_url'] = Config::host()['resource'].$result['s_photo1'];
                } else {
                    $row_array['my_chat_start'] = $result['c_second_user_start'];
                    $row_array['oppo_chat_start'] = $result['c_first_user_start'];
                    $row_array['my_chat_matched'] = $result['c_second_user_matched'];
                    $row_array['oppo_chat_matched'] = $result['c_first_user_matched'];
                    $row_array['oppo_user_id'] = $result['c_first_user_id'];
                    $row_array['oppo_user_name'] = $result['f_firstname'];
                    $row_array['my_message_count'] = $result['c_second_message_count'];
                    $row_array['other_message_count'] = $result['c_first_message_count'];
                    $row_array['my_answer'] = $result['c_second_user_answer'];
                    $row_array['other_answer'] = $result['c_first_user_answer'];
                    $row_array['image_url'] = Config::host()['resource'].$result['f_photo1'];
                }
                $row_array['friendship'] = self::calculateFriendShip($result['c_first_message_count'], $result['c_second_message_count']);
                $row_array['room_status'] = $result['c_room_status'];
                $row_array['create_date'] = $result['c_create_date'];
                $row_array['terminate_time'] = $result['c_terminate_time'];

                $selectSql = "select count(*) as count_message from tbl_chat_content where chat_id = :chat_id";
                $stmt = parent::database()->prepare($selectSql);
                $stmt->bindParam("chat_id", $chat_id);
                $stmt->execute();
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                $count_message = $result['count_message'];

                $message_array = array();
                $selectSql = "select * from tbl_chat_content where chat_id = :chat_id";
                $selectSql .= " order by id asc";
                if ($count_message > 30) {
                    $count_message_min = $count_message - 30;
                    $selectSql .= " limit $count_message_min, $count_message";
                }
                $stmt = parent::database()->prepare($selectSql);
                $stmt->bindParam("chat_id", $chat_id);
                $stmt->execute();
                $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                for ($i = 0; $i < count($result); $i++) {
                    if ($result[$i]['user_id'] == $user_id) {
                        $message_obj['is_send_by_me'] = true;
                    } else {
                        $message_obj['is_send_by_me'] = false;
                    }
                    $message_obj['message_id'] = $result[$i]['id'];
                    $message_obj['message'] = $result[$i]['message'];
                    $message_obj['message_type'] = $result[$i]['message_status'];
                    $message_obj['receive_check'] = $result[$i]['receive_check'];

                    array_push($message_array, $message_obj);
                }
                $row_array['messagelist'] = $message_array;
            } catch (\PDOException $e) {
                return parent::abort(300, $selectSql.$e->getMessage());
            }

            return parent::response($row_array);
        } else {
            try {
                $selectSql = "select c.id as c_id, c.first_answer as c_first_answer, c.question_id as c_question_id, q.question as q_question
                          from tbl_blind_chat c
                          left join tbl_question q on q.id = c.question_id
                          where c.id = :chat_id";
                $stmt = parent::database()->prepare($selectSql);
                $stmt->bindParam("chat_id", $chat_id);
                $stmt->execute();
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);

                $row_array['is_chat_begin'] = 0;
                $row_array['chat_id'] = $result['c_id'];
                $row_array['question_id'] = $result['c_question_id'];
                $row_array['question'] = $result['q_question'];
                $row_array['oppo_user_id'] = 0;
                $row_array['oppo_user_name'] = "";
                $row_array['my_answer'] = $result['c_first_answer'];
                $row_array['other_answer'] = '';
                $row_array['my_message_count'] = 0;
                $row_array['other_message_count'] = 0;
                $row_array['image_url'] = "";
                $row_array['create_date'] = "";
                $row_array['terminate_time'] = "";

                $row_array['friendship'] = 0;
                $row_array['room_status'] = self::$CHAT_ROOM_NORMAL;

                $message_array = array();

                $row_array['messagelist'] = $message_array;
            } catch (\PDOException $e) {
                return parent::abort(300, $selectSql.$e->getMessage());
            }

            return parent::response($row_array);
        }
    }

    public function getChatInform()
    {
        $user_id = parent::param('user_id'); //user id
        $chat_id = parent::param('chat_id'); //chat room id

        try {
            if (!self::isValidUser($user_id)) {
                return parent::abort(400, "Request from invalid user.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        try {
            if (!self::isValidChatRoom($chat_id)) {
                return parent::abort(400, "Invalid chat room.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        try {
            $selectSql = "select *
                              from tbl_chat_list c
                              where id = :chat_id";
            $stmt = parent::database()->prepare($selectSql);
            $stmt->bindParam("chat_id", $chat_id);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            $row_array['friendship'] = self::calculateFriendShip($result['first_message_count'], $result['second_message_count']);
            $row_array['create_date'] = $result['create_date'];
            $row_array['last_message_time'] = $result['last_chat_time'];
            $row_array['message_count'] = $result['first_message_count'] + $result['second_message_count'];
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        return parent::response($row_array);
    }

    public function getProfile()
    {
        $user_id = parent::param('user_id'); //user id
        $chat_id = parent::param('chat_id'); //chat room id
        $oppo_user_id = parent::param('oppo_user_id'); //oppo user id

        try {
            if (!self::isValidUser($user_id)) {
                return parent::abort(400, "Request from invalid user.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        try {
            if (!self::isValidUser($oppo_user_id)) {
                return parent::abort(400, "Request to invalid user.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        try {
            if (!self::isValidChatRoom($chat_id)) {
                return parent::abort(400, "Invalid chat room.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        $stmt = parent::database()->prepare("SELECT * FROM `tbl_user` WHERE id = $user_id");
        $stmt->execute();
        $result_me = $stmt->fetch(\PDO::FETCH_ASSOC);

        try {
            $selectSql = "select *
                              from tbl_chat_list c
                              where id = :chat_id";
            $stmt = parent::database()->prepare($selectSql);
            $stmt->bindParam("chat_id", $chat_id);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            $row_array['friendship'] = self::calculateFriendShip($result['first_message_count'], $result['second_message_count']);

            $selectSql = "select *
                              from tbl_user
                              where id = :user_id";
            $stmt = parent::database()->prepare($selectSql);
            $stmt->bindParam("user_id", $oppo_user_id);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            $row_array['oppo_user_name'] = $result['firstname'];
            $row_array['oppo_user_age'] = $result['age'];
            $row_array['oppo_user_address'] = $result['address'];
            $row_array['distance'] = self::geoDistance($result_me['latitude'], $result_me['longitude'], $result['latitude'], $result['longitude']);
            if ($result['photo1'] != null)
                $row_array['photo1'] = Config::host()['resource'].$result['photo1'];
            if ($result['photo2'] != null)
                $row_array['photo2'] = Config::host()['resource'].$result['photo2'];
            if ($result['photo3'] != null)
                $row_array['photo3'] = Config::host()['resource'].$result['photo3'];
            if ($result['photo4'] != null)
                $row_array['photo4'] = Config::host()['resource'].$result['photo4'];
            if ($result['photo5'] != null)
                $row_array['photo5'] = Config::host()['resource'].$result['photo5'];
            if ($result['photo6'] != null)
                $row_array['photo6'] = Config::host()['resource'].$result['photo6'];
            $row_array['is_allow_sns'] = $result['setting_show_sns'];
            $row_array['acc_Facebook'] = $result['facebook_id'];
            $row_array['acc_Instagram'] = $result['acc_Instagram'];
            $row_array['acc_Instagram_name'] = $result['acc_Instagram_name'];
            $row_array['acc_check_Instagram'] = $result['acc_check_Instagram'];
            $row_array['acc_Twitter'] = $result['acc_Twitter'];
            $row_array['acc_Twitter_name'] = $result['acc_Twitter_name'];
            $row_array['acc_check_Twitter'] = $result['acc_check_Twitter'];
            $row_array['acc_Snapchat'] = $result['acc_Snapchat'];
            $row_array['acc_Snapchat_name'] = $result['acc_Snapchat_name'];
            $row_array['acc_check_Snapchat'] = $result['acc_check_Snapchat'];
            $row_array['acc_Youtube'] = $result['acc_Youtube'];
            $row_array['acc_Youtube_name'] = $result['acc_Youtube_name'];
            $row_array['acc_check_Youtube'] = $result['acc_check_Youtube'];
            $row_array['acc_Tumblr'] = $result['acc_Tumblr'];
            $row_array['acc_Tumblr_name'] = $result['acc_Tumblr_name'];
            $row_array['acc_check_Tumblr'] = $result['acc_check_Tumblr'];
            $row_array['acc_Pinterest'] = $result['acc_Pinterest'];
            $row_array['acc_Pinterest_name'] = $result['acc_Pinterest_name'];
            $row_array['acc_check_Pinterest'] = $result['acc_check_Pinterest'];
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        return parent::response($row_array);
    }

    public function reportUser()
    {
        $user_id = parent::param('user_id'); //user id
        $chat_id = parent::param('chat_id'); //chat room id
        $oppo_user_id = parent::param('oppo_user_id'); //oppo user id
        $reason = parent::param('reason'); //boolean

        try {
            if (!self::isValidUser($user_id)) {
                return parent::abort(400, "Request from invalid user.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        try {
            if (!self::isValidUser($oppo_user_id)) {
                return parent::abort(400, "Request to invalid user.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        try {
            if (!self::isValidChatRoom($chat_id)) {
                return parent::abort(400, "Invalid chat room.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        try {
            parent::database()->beginTransaction();
            $insertSql = "INSERT INTO `tbl_report`
                                (`user_id`,
                                `oppo_user_id`,
                                `chat_id`,
                                `reason`,
                                `create_date`)
                                VALUES
                                (:user_id,
                                :oppo_user_id,
                                :chat_id,
                                :reason,
                                now())";
            $stmt = parent::database()->prepare($insertSql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->bindParam("oppo_user_id", $oppo_user_id);
            $stmt->bindParam("chat_id", $chat_id);
            $stmt->bindParam("reason", $reason);
            $stmt->execute();
            parent::database()->commit();
        } catch (\PDOException $e) {
            parent::database()->rollBack();

            return -1;
        }
    }

    public function connectUser()
    {
        $user_id = parent::param('user_id'); //user id
        $chat_id = parent::param('chat_id'); //chat room id

        try {
            if (!self::isValidUser($user_id)) {
                return parent::abort(400, "Request from invalid user.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        try {
            if (!self::isValidChatRoom($chat_id)) {
                return parent::abort(400, "Invalid chat room.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        if (self::getMatchListCount($user_id) >= 5) {
            return parent::abort(401, "Match chat count exceed .");
        }

        try {
            parent::database()->beginTransaction();
            $updateSql = "UPDATE `tbl_chat_list`
                            SET
                            `first_user_matched` = IF (first_user_id = :user_id,  1, `first_user_matched`),
                            `second_user_matched` = IF (second_user_id = :user_id,  1, `second_user_matched`)
                            WHERE `id` = :chat_id";
            $stmt = parent::database()->prepare($updateSql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->bindParam("chat_id", $chat_id);
            $stmt->execute();
            parent::database()->commit();
        } catch (\PDOException $e) {
            parent::database()->rollBack();
            return parent::abort(300, $e->getMessage());
        }

        try {
            $oppo_user_id = $this->getOppoUserId($user_id, $chat_id);

            // PUSH MUST CHECK
            $push = new PushController();
            $push->sendPushConnect($oppo_user_id, $chat_id);
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        parent::response("success");
    }

    public function requestDisconnect()
    {
        $user_id = parent::param('user_id'); //user id
        $chat_id = parent::param('chat_id'); //chat room id
        $message = parent::param('message'); //message

        try {
            if (!self::isValidUser($user_id)) {
                return parent::abort(400, "Request from invalid user.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        try {
            if (!self::isValidChatRoom($chat_id)) {
                return parent::abort(400, "Invalid chat room.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        try {
            $message_id = self::insertNewChatContent($chat_id, $user_id, $message, self::$CHAT_ROOM_REQUEST_DISCONNECT);
            if (!$message_id)
                return parent::abort(400, "Invalid Room Status Request.");

            $oppo_user_id = self::getOppoUserId($user_id, $chat_id);

            // PUSH MUST CHECK
            $push = new PushController();
            $push->sendPushRequestDisconnect($oppo_user_id, $chat_id, $message, !$this->isMatchedChat($chat_id));
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        parent::response("success");
    }

    public function sendLastChance()
    {
        $user_id = parent::param('user_id'); //user id
        $chat_id = parent::param('chat_id'); //chat room id
        $message = parent::param('message'); //message

        try {
            if (!self::isValidUser($user_id)) {
                return parent::abort(400, "Request from invalid user.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        try {
            if (!self::isValidChatRoom($chat_id)) {
                return parent::abort(400, "Invalid chat room.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        try {
            $message_id = self::insertNewChatContent($chat_id, $user_id, $message, self::$CHAT_ROOM_LAST_CHANCE);
            if (!$message_id)
                return parent::abort(400, "Invalid Room Status Request.");

            $oppo_user_id = self::getOppoUserId($user_id, $chat_id);

            $selectSql = "SELECT photo1, firstname, lastname, gender FROM `tbl_user` WHERE id = :user_id";
            $stmt = parent::database()->prepare($selectSql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            // PUSH MUST CHECK
            $push = new PushController();
            $push->sendPushLastChance($oppo_user_id, $chat_id, $message, !$this->isMatchedChat($chat_id),
                $result["firstname"], Config::host()['resource'].$result["photo1"], $result["gender"]);
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        parent::response("Success");
    }

    public function reconnect()
    {
        $user_id = parent::param('user_id'); //user id
        $chat_id = parent::param('chat_id'); //chat room id

        try {
            if (!self::isValidUser($user_id)) {
                return parent::abort(400, "Request from invalid user.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        try {
            if (!self::isValidChatRoom($chat_id)) {
                return parent::abort(400, "Invalid chat room.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        try {
            $message_id = self::insertNewChatContent($chat_id, $user_id, null, self::$CHAT_ROOM_RECONNECTED);
            if (!$message_id)
                return parent::abort(400, "Invalid Room Status Request.");

            $oppo_user_id = self::getOppoUserId($user_id, $chat_id);
            // PUSH MUST CHECK
            $push = new PushController();
            $push->sendPushReconnect($oppo_user_id, $chat_id, !$this->isMatchedChat($chat_id));
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        parent::response("Success");
    }

    public function terminate()
    {
        $user_id = parent::param('user_id'); //user id
        $chat_id = parent::param('chat_id'); //chat room id
        $message = parent::param('message'); //message

        try {
            if (!self::isValidUser($user_id)) {
                return parent::abort(400, "Request from invalid user.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        try {
            if (!self::isValidChatRoom($chat_id)) {
                return parent::abort(400, "Invalid chat room.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        try {
            $message_id = self::insertNewChatContent($chat_id, $user_id, $message, self::$CHAT_ROOM_CONFIRM_DISCONNECT);
            if (!$message_id)
                return parent::abort(400, "Invalid Room Status Request.");

            $oppo_user_id = self::getOppoUserId($user_id, $chat_id);

            $selectSql = "SELECT photo1, firstname, lastname FROM `tbl_user` WHERE id = :user_id";
            $stmt = parent::database()->prepare($selectSql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            // PUSH MUST CHECK
            $push = new PushController();
            $push->sendPushConfirmDisconnect($oppo_user_id, $chat_id, $message, $result["firstname"], !$this->isMatchedChat($chat_id));
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        parent::response("Success");
    }

    public function checkTerminate()
    {
        $user_id = parent::param('user_id'); //user id
        $chat_id = parent::param('chat_id'); //chat room id

        try {
            if (!self::isValidUser($user_id)) {
                return parent::abort(400, "Request from invalid user.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        try {
            if (!self::isValidChatRoom($chat_id)) {
                return parent::abort(400, "Invalid chat room.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        try {
            parent::database()->beginTransaction();
            $deleteSql = "DELETE from `tbl_chat_list` WHERE `id` = :chat_id";
            $stmt = parent::database()->prepare($deleteSql);
            $stmt->bindParam("chat_id", $chat_id);
            $stmt->execute();
            parent::database()->commit();
        } catch (\PDOException $e) {
            parent::database()->rollBack();
            return parent::abort(300, $e->getMessage());
        }

        try {
            parent::database()->beginTransaction();
            $deleteSql = "DELETE from `tbl_chat_content` WHERE `chat_id` = :chat_id";
            $stmt = parent::database()->prepare($deleteSql);
            $stmt->bindParam("chat_id", $chat_id);
            $stmt->execute();
            parent::database()->commit();
        } catch (\PDOException $e) {
            parent::database()->rollBack();
            return parent::abort(300, $e->getMessage());
        }

        parent::response("Success");
    }

    public function sendOnlineState()
    {
        $user_id = parent::param('user_id'); //user id
        $online_state = parent::param('online_state'); //chat room id
        $latitude = parent::param('latitude'); //latitude
        $longitude = parent::param('longitude'); //longitude

        try {
            if (!self::isValidUser($user_id)) {
                return parent::abort(400, "Request from invalid user.");
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        try {
            parent::database()->beginTransaction();
            $updateSql = "UPDATE `tbl_user`
                        SET
                        `is_online` = :is_online,
                        `latitude` = :latitude,
                        `longitude` = :longitude,
                        `last_update_time` = now(),
                        WHERE `id` = :user_id";
            $stmt = parent::database()->prepare($updateSql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->bindParam("is_online", $online_state);
            $stmt->bindParam("latitude", $latitude);
            $stmt->bindParam("longitude", $longitude);
            $stmt->execute();
            parent::database()->commit();
        } catch (\PDOException $e) {
            parent::database()->rollBack();
        }

        // PUSH MUST CHECK
        $push = new PushController();
        $selectSql = "select id, first_user_id, second_user_id from tbl_chat_list
                          where first_user_id = :user_id or second_user_id = :user_id";
        $stmt = parent::database()->prepare($selectSql);
        $stmt->bindParam("user_id", $user_id);
        $stmt->execute();
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        for ($i = 0; $i < count($result); $i++) {
            $chat_id = $result[$i]['id'];
            if ($result[$i]['first_user_id'] == $user_id) {
                $oppo_user_id = $result[$i]['second_user_id'];
            } else {
                $oppo_user_id = $result[$i]['first_user_id'];
            }

            $push->sendPushOnlineState($oppo_user_id, $chat_id, $user_id, $online_state);
        }

        parent::response("Success");
    }
}
