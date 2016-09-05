<?php
/**
 * User: Sasaki Kenski
 * Date: 2016-03-03
 */

namespace App\Controller;

use App\Config;
use Firebase\JWT\JWT;

class UserController extends BaseController
{
    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
    }

    public static function getUserInformSimple($user_id)
    {
        $selectQuery = "select * from `tbl_user` where id = :user_id";
        $stmt = parent::database()->prepare($selectQuery);
        $stmt->bindParam("user_id", $user_id);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($result == null) {
            return parent::abort(400, $user_id." is invalid user id.");
        }

        $json_result = [
            'platform' => $result['platform'],
            'dev_uuid' => $result['dev_uuid'],
        ];

        return $json_result;
    }

    private function getUserInform($user_id, $token = null)
    {
        try {
            $selectQuery = "select * from `tbl_user` where id = :user_id";
            $stmt = parent::database()->prepare($selectQuery);
            $stmt->bindParam("user_id", $user_id);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result == null) {
                return parent::abort(400, $user_id." is invalid user id.");
            }

            $json_result = [
                'user_id' => $result['id'], //user id
                'facebook_id' => $result['facebook_id'], //facebookid
                'firstname' => $result['firstname'],
                'lastname' => $result['lastname'],
                'address' => $result['address'],
                'gender' => $result['gender'],
                'age' => $result['age'],
                'acc_Instagram' => $result['acc_Instagram'], //Instagramuser id
                'acc_Instagram_name' => $result['acc_Instagram_name'], //Instagramuser name
                'acc_check_Instagram' => $result['acc_check_Instagram'], //Instagram
                'acc_Twitter' => $result['acc_Twitter'], //Twitteruser id
                'acc_Twitter_name' => $result['acc_Twitter_name'], //Twitteruser name
                'acc_check_Twitter' => $result['acc_check_Twitter'], //Twitter
                'acc_Snapchat' => $result['acc_Snapchat'], //Snapchat user id
                'acc_Snapchat_name' => $result['acc_Snapchat_name'], //Snapchat user name
                'acc_check_Snapchat' => $result['acc_check_Snapchat'], //Snapchat
                'acc_Youtube' => $result['acc_Youtube'], //Youtube user id
                'acc_Youtube_name' => $result['acc_Youtube_name'], //Youtube user name
                'acc_check_Youtube' => $result['acc_check_Youtube'], //Youtube
                'acc_Tumblr' => $result['acc_Tumblr'], //Tumblr user id
                'acc_Tumblr_name' => $result['acc_Tumblr_name'], //Tumblr user name
                'acc_check_Tumblr' => $result['acc_check_Tumblr'], //Tumblr
                'acc_Pinterest' => $result['acc_Pinterest'], //Pinterest user id
                'acc_Pinterest_name' => $result['acc_Pinterest_name'], //Pinterest user name
                'acc_check_Pinterest' => $result['acc_check_Pinterest'], //Pinterest
                'setting_push_notification' => $result['setting_push_notification'], //PushNotification
                'setting_distance' => $result['setting_distance'], 
                'setting_age_min' => $result['setting_age_min'], 
                'setting_age_max' => $result['setting_age_max'], 
                'setting_gender' => $result['setting_gender'], 
                'setting_show_sns' => $result['setting_show_sns'],
            ];

            if ($result['photo1'] != null)
                $json_result['photo1'] = Config::host()['resource'].$result['photo1'];
            if ($result['photo2'] != null)
                $json_result['photo2'] = Config::host()['resource'].$result['photo2'];
            if ($result['photo3'] != null)
                $json_result['photo3'] = Config::host()['resource'].$result['photo3'];
            if ($result['photo4'] != null)
                $json_result['photo4'] = Config::host()['resource'].$result['photo4'];
            if ($result['photo5'] != null)
                $json_result['photo5'] = Config::host()['resource'].$result['photo5'];
            if ($result['photo6'] != null)
                $json_result['photo6'] = Config::host()['resource'].$result['photo6'];

            if ($token)
                $json_result['token'] = $token;

            return parent::response($json_result);
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }
    }

    public function signin()
    {
        $facebookID = parent::param('facebookID');
        $firstname = parent::param('firstname');
        $lastname = parent::param('lastname');
        $address = parent::param('address');
        $gender = parent::param('gender');
        $age = parent::param('age');
        $platform = parent::param('platform');
        $dev_uuid = parent::param('dev_uuid');
        $latitude = parent::param('latitude');
        $longitude = parent::param('longitude');
        $picture_data = parent::param('photo');

        try {
            $stmt = parent::database()->prepare("SELECT id, is_login, dev_uuid, photo1 FROM `tbl_user` WHERE facebook_id = :facebookId");
            $stmt->bindParam("facebookId", $facebookID);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($result != null) {
                if ($result["is_login"] == 1 && $result["dev_uuid"] != $dev_uuid) { 
                    return parent::abort(400, "Already logged in with other phone.");
                }

                $user_id = $result["id"];
                $old_picture_name = $result["photo1"];

                $new_picture_name = uniqid('user_img/img-' . date('Ymd') . '-') . '.jpg';
                $new_picture_path = getcwd() . '/resources/' . $new_picture_name;
                if (!file_put_contents($new_picture_path, base64_decode($picture_data))) {
                    return parent::abort(400, 'Photo save error.');
                }

                try {
                    parent::database()->beginTransaction();
                    $updateSql = "UPDATE `tbl_user`
                                SET
                                `firstname` = :firstname,
                                `lastname` = :lastname,
                                `address` = :address,
                                `gender` = :gender,
                                `age` = :age,
                                `photo1` = :photo,
                                `platform` = :platform,
                                `dev_uuid` = :dev_uuid,
                                `latitude` = :latitude,
                                `longitude` = :longitude,
                                `last_update_time` = now(),
                                `is_login` = 1
                                WHERE `id` = :user_id";
                    $stmt = parent::database()->prepare($updateSql);
                    $stmt->bindParam("firstname", $firstname);
                    $stmt->bindParam("lastname", $lastname);
                    $stmt->bindParam("address", $address);
                    $stmt->bindParam("gender", $gender);
                    $stmt->bindParam("age", $age);
                    $stmt->bindParam("photo", $new_picture_name);
                    $stmt->bindParam("platform", $platform);
                    $stmt->bindParam("dev_uuid", $dev_uuid);
                    $stmt->bindParam("latitude", $latitude);
                    $stmt->bindParam("longitude", $longitude);
                    $stmt->bindParam("user_id", $user_id);
                    $stmt->execute();

                    parent::database()->commit();

                    if ($old_picture_name != null) {
                        $old_picture_path = getcwd() . '/resources/' . $old_picture_name;
                        @unlink($old_picture_path);
                    }
                } catch (\PDOException $e) {
                    parent::database()->rollBack();

                    @unlink($new_picture_path);

                    return parent::abort(300, $e->getMessage());
                }
            } else {
                $new_picture_name = uniqid('user_img/img-' . date('Ymd') . '-') . '.jpg';
                $new_picture_path = getcwd() . '/resources/' . $new_picture_name;
                if (!file_put_contents($new_picture_path, base64_decode($picture_data))) {
                    return parent::abort(400, 'Photo save error.');
                }

                try {
                    parent::database()->beginTransaction();
                    $insertSql = "INSERT INTO `tbl_user`
                    (`facebook_id`,
                    `firstname`,
                    `lastname`,
                    `address`,
                    `gender`,
                    `age`,
                    `photo1`,
                    `platform`,
                    `dev_uuid`,
                    `latitude`,
                    `longitude`,
                    `last_update_time`,
                    `is_login`)
                    VALUES
                    (:facebook_id,
                    :firstname,
                    :lastname,
                    :address,
                    :gender,
                    :age,
                    :photo,
                    :platform,
                    :dev_uuid,
                    :latitude,
                    :longitude,
                    now(),
                    1)";
                    $stmt = parent::database()->prepare($insertSql);
                    $stmt->bindParam("facebook_id", $facebookID);
                    $stmt->bindParam("firstname", $firstname);
                    $stmt->bindParam("lastname", $lastname);
                    $stmt->bindParam("address", $address);
                    $stmt->bindParam("gender", $gender);
                    $stmt->bindParam("age", $age);
                    $stmt->bindParam("photo", $new_picture_name);
                    $stmt->bindParam("platform", $platform);
                    $stmt->bindParam("dev_uuid", $dev_uuid);
                    $stmt->bindParam("latitude", $latitude);
                    $stmt->bindParam("longitude", $longitude);
                    $stmt->execute();
                    parent::database()->commit();

                    $stmt = parent::database()->prepare("SELECT id FROM `tbl_user` WHERE facebook_id = :facebookId");
                    $stmt->bindParam("facebookId", $facebookID);
                    $stmt->execute();
                    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                    $user_id = $result["id"];
                } catch (\PDOException $e) {
                    parent::database()->rollBack();

                    @unlink($new_picture_path);

                    return parent::abort(300, $e->getMessage());
                }
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        $now = new \DateTime();
        $future = new \DateTime("now +20 year");
        $payload = [
            "iat" => $now->getTimeStamp(),
            "exp" => $future->getTimeStamp(),
            "user_id" => $user_id,
        ];
        $secret = getenv("JWT_SECRET");
        $token = JWT::encode($payload, $secret, "HS256");

        return self::getUserInform($user_id, $token);
    }

    public function changeSNS()
    {
        $user_id = parent::param('user_id'); //user id
        $site_name = parent::param('site_name');
        $site_enabled = parent::param('site_enabled');
        $site_userid = parent::param('site_userid');
        $site_username = parent::param('site_username');

        if ($site_name == "Instagram") {
            $updateSql = "UPDATE `tbl_user`
                            SET
                            `acc_Instagram` = :acc_id,
                            `acc_Instagram_name` = :acc_name,
                            `acc_check_Instagram` = :acc_check
                            WHERE `id` = :user_id";
        } else if ($site_name == "Twitter") {
            $updateSql = "UPDATE `tbl_user`
                            SET
                            `acc_Twitter` = :acc_id,
                            `acc_Twitter_name` = :acc_name,
                            `acc_check_Twitter` = :acc_check
                            WHERE `id` = :user_id";
        } else if ($site_name == "Snapchat") {
            $updateSql = "UPDATE `tbl_user`
                            SET
                            `acc_Snapchat` = :acc_id,
                            `acc_Snapchat_name` = :acc_name,
                            `acc_check_Snapchat` = :acc_check
                            WHERE `id` = :user_id";
        } else if ($site_name == "Youtube") {
            $updateSql = "UPDATE `tbl_user`
                            SET
                            `acc_Youtube` = :acc_id,
                            `acc_Youtube_name` = :acc_name,
                            `acc_check_Youtube` = :acc_check
                            WHERE `id` = :user_id";
        } else if ($site_name == "Tumblr") {
            $updateSql = "UPDATE `tbl_user`
                            SET
                            `acc_Tumblr` = :acc_id,
                            `acc_Tumblr_name` = :acc_name,
                            `acc_check_Tumblr` = :acc_check
                            WHERE `id` = :user_id";
        } else if ($site_name == "Pinterest") {
            $updateSql = "UPDATE `tbl_user`
                            SET
                            `acc_Pinterest` = :acc_id,
                            `acc_Pinterest_name` = :acc_name,
                            `acc_check_Pinterest` = :acc_check
                            WHERE `id` = :user_id";
        } else {
            return parent::abort(400, "Invalid site name");
        }

        if ($updateSql != "") {
            try {
                parent::database()->beginTransaction();
                $stmt = parent::database()->prepare($updateSql);
                $stmt->bindParam("acc_id", $site_userid);
                $stmt->bindParam("acc_name", $site_username);
                $stmt->bindParam("acc_check", $site_enabled);
                $stmt->bindParam("user_id", $user_id);
                $stmt->execute();
                parent::database()->commit();
            } catch (\PDOException $e) {
                parent::database()->rollBack();
                return parent::abort(300, $e->getMessage());
            }
        }

        return self::getUserInform($user_id);
    }

    public function saveSNSList()
    {
        $user_id = parent::param('user_id'); //user id
        $site_names = parent::param('site_names');
        $site_enableds = parent::param('site_enabled');
        $site_userids = parent::param('site_userids');
        $site_usernames = parent::param('site_usernames');

        for ($i = 0 ; $i < count($site_names) ; $i ++) {
            $site_name = $site_names[$i];
            $site_enabled = $site_enableds[$i];
            $site_userid = $site_userids[$i];
            $site_username = $site_usernames[i];

            if ($site_name == "Instagram") {
                $updateSql = "UPDATE `tbl_user`
                            SET
                            `acc_Instagram` = :acc_id,
                            `acc_Instagram_name` = :acc_name,
                            `acc_check_Instagram` = :acc_check
                            WHERE `id` = :user_id";
            } else if ($site_name == "Twitter") {
                $updateSql = "UPDATE `tbl_user`
                            SET
                            `acc_Twitter` = :acc_id,
                            `acc_Twitter_name` = :acc_name,
                            `acc_check_Twitter` = :acc_check
                            WHERE `id` = :user_id";
            } else if ($site_name == "Snapchat") {
                $updateSql = "UPDATE `tbl_user`
                            SET
                            `acc_Snapchat` = :acc_id,
                            `acc_Snapchat_name` = :acc_name,
                            `acc_check_Snapchat` = :acc_check
                            WHERE `id` = :user_id";
            } else if ($site_name == "Youtube") {
                $updateSql = "UPDATE `tbl_user`
                            SET
                            `acc_Youtube` = :acc_id,
                            `acc_Youtube_name` = :acc_name,
                            `acc_check_Youtube` = :acc_check
                            WHERE `id` = :user_id";
            } else if ($site_name == "Tumblr") {
                $updateSql = "UPDATE `tbl_user`
                            SET
                            `acc_Tumblr` = :acc_id,
                            `acc_Tumblr_name` = :acc_name,
                            `acc_check_Tumblr` = :acc_check
                            WHERE `id` = :user_id";
            } else if ($site_name == "Pinterest") {
                $updateSql = "UPDATE `tbl_user`
                            SET
                            `acc_Pinterest` = :acc_id,
                            `acc_Pinterest_name` = :acc_name,
                            `acc_check_Pinterest` = :acc_check
                            WHERE `id` = :user_id";
            } else {
                return parent::abort(400, "Invalid site name");
            }

            if ($updateSql != "") {
                try {
                    parent::database()->beginTransaction();
                    $stmt = parent::database()->prepare($updateSql);
                    $stmt->bindParam("acc_id", $site_userid);
                    $stmt->bindParam("acc_name", $site_username);
                    $stmt->bindParam("acc_check", $site_enabled);
                    $stmt->bindParam("user_id", $user_id);
                    $stmt->execute();
                    parent::database()->commit();
                } catch (\PDOException $e) {
                    parent::database()->rollBack();
                    return parent::abort(300, $e->getMessage());
                }
            }
        }

        return self::getUserInform($user_id);
    }

    public function addPicture()
    {
        $user_id = parent::param('user_id'); //user id
        $index = parent::param('index');
        $picture_data = parent::param('picture_data');

        if ($index == 1) {
            $selectSql = "SELECT photo1 as photo FROM `tbl_user` WHERE id = :user_id";
        } else if ($index == 2) {
            $selectSql = "SELECT photo2 as photo FROM `tbl_user` WHERE id = :user_id";
        } else if ($index == 3) {
            $selectSql = "SELECT photo3 as photo FROM `tbl_user` WHERE id = :user_id";
        } else if ($index == 4) {
            $selectSql = "SELECT photo4 as photo FROM `tbl_user` WHERE id = :user_id";
        } else if ($index == 5) {
            $selectSql = "SELECT photo5 as photo FROM `tbl_user` WHERE id = :user_id";
        } else if ($index == 6) {
            $selectSql = "SELECT photo6 as photo FROM `tbl_user` WHERE id = :user_id";
        } else {
            return parent::abort(400, "Invalid image index.");
        }
        if ($selectSql != "") {
            $stmt = parent::database()->prepare($selectSql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($result["photo"] != null) {
                $old_path = getcwd() . '/resources/' . $result["photo"];
                @unlink($old_path);
            }
        }

        $img_name = uniqid('user_img/img-' . date('Ymd') . '-') . '.jpg';
        $img_path = getcwd() . '/resources/' . $img_name;
        if (!file_put_contents($img_path, base64_decode($picture_data))) {
            return parent::abort(400, 'Photo save error.');
        }

        if ($index == 1) {
            $updateSql = "UPDATE `tbl_user` SET `photo1` = :photo WHERE id = :user_id";
        } else if ($index == 2) {
            $updateSql = "UPDATE `tbl_user` SET `photo2` = :photo WHERE id = :user_id";
        } else if ($index == 3) {
            $updateSql = "UPDATE `tbl_user` SET `photo3` = :photo WHERE id = :user_id";
        } else if ($index == 4) {
            $updateSql = "UPDATE `tbl_user` SET `photo4` = :photo WHERE id = :user_id";
        } else if ($index == 5) {
            $updateSql = "UPDATE `tbl_user` SET `photo5` = :photo WHERE id = :user_id";
        } else if ($index == 6) {
            $updateSql = "UPDATE `tbl_user` SET `photo6` = :photo WHERE id = :user_id";
        } else {
            return parent::abort(400, "Invalid image index.");
        }

        if ($updateSql != "") {
            try {
                parent::database()->beginTransaction();
                $stmt = parent::database()->prepare($updateSql);
                $stmt->bindParam("photo", $img_name);
                $stmt->bindParam("user_id", $user_id);
                $stmt->execute();
                parent::database()->commit();
            } catch (\PDOException $e) {
                parent::database()->rollBack();
                return parent::abort(300, $e->getMessage());
            }
        }

        return self::getUserInform($user_id);
    }

    public function removePicture() {
        $user_id = parent::param('user_id'); //user id
        $index = parent::param('index');

        if ($index == 1) {
            $selectSql = "SELECT photo1 as photo FROM `tbl_user` WHERE id = :user_id";
        } else if ($index == 2) {
            $selectSql = "SELECT photo2 as photo FROM `tbl_user` WHERE id = :user_id";
        } else if ($index == 3) {
            $selectSql = "SELECT photo3 as photo FROM `tbl_user` WHERE id = :user_id";
        } else if ($index == 4) {
            $selectSql = "SELECT photo4 as photo FROM `tbl_user` WHERE id = :user_id";
        } else if ($index == 5) {
            $selectSql = "SELECT photo5 as photo FROM `tbl_user` WHERE id = :user_id";
        } else if ($index == 6) {
            $selectSql = "SELECT photo6 as photo FROM `tbl_user` WHERE id = :user_id";
        } else {
            return parent::abort(400, "Invalid image index.");
        }

        if ($selectSql != "") {
            $stmt = parent::database()->prepare($selectSql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($result["photo"] != null) {
                $old_path = getcwd() . '/resources/' . $result["photo"];
                @unlink($old_path);
            }
        }

        if ($index == 1) {
            $updateSql = "UPDATE `tbl_user` SET `photo1` = NULL WHERE id = :user_id";
        } else if ($index == 2) {
            $updateSql = "UPDATE `tbl_user` SET `photo2` = NULL WHERE id = :user_id";
        } else if ($index == 3) {
            $updateSql = "UPDATE `tbl_user` SET `photo3` = NULL WHERE id = :user_id";
        } else if ($index == 4) {
            $updateSql = "UPDATE `tbl_user` SET `photo4` = NULL WHERE id = :user_id";
        } else if ($index == 5) {
            $updateSql = "UPDATE `tbl_user` SET `photo5` = NULL WHERE id = :user_id";
        } else if ($index == 6) {
            $updateSql = "UPDATE `tbl_user` SET `photo6` = NULL WHERE id = :user_id";
        } else {
            return parent::abort(400, "Invalid image index.");
        }

        if ($updateSql != "") {
            try {
                parent::database()->beginTransaction();
                $stmt = parent::database()->prepare($updateSql);
                $stmt->bindParam("user_id", $user_id);
                $stmt->execute();
                parent::database()->commit();
            } catch (\PDOException $e) {
                parent::database()->rollBack();
                return parent::abort(300, $updateSql."-----".$e->getMessage());
            }
        }

        return self::getUserInform($user_id);
    }

    public function changeSetting()
    {
        $user_id = parent::param('user_id'); //user id
        $setting_push_notification = parent::param('setting_push_notification');
        $setting_distance = parent::param('setting_distance');
        $setting_age_min = parent::param('setting_age_min');
        $setting_age_max = parent::param('setting_age_max');
        $setting_gender = parent::param('setting_gender');
        $setting_show_sns = parent::param('setting_show_sns');

        try {
            parent::database()->beginTransaction();
            $updateSql = "UPDATE `tbl_user`
                            SET
                            `setting_push_notification` = :setting_push_notification,
                            `setting_distance` = :setting_distance,
                            `setting_age_min` = :setting_age_min,
                            `setting_age_max` = :setting_age_max,
                            `setting_gender` = :setting_gender,
                            `setting_show_sns` = :setting_show_sns,
                            `last_update_time` = now()
                            WHERE `id` = :user_id";
            $stmt = parent::database()->prepare($updateSql);
            $stmt->bindParam("setting_push_notification", $setting_push_notification);
            $stmt->bindParam("setting_distance", $setting_distance);
            $stmt->bindParam("setting_age_min", $setting_age_min);
            $stmt->bindParam("setting_age_max", $setting_age_max);
            $stmt->bindParam("setting_gender", $setting_gender);
            $stmt->bindParam("setting_show_sns", $setting_show_sns);
            $stmt->bindParam("user_id", $user_id);
            $stmt->execute();
            parent::database()->commit();
        } catch (\PDOException $e) {
            parent::database()->rollBack();
            return parent::abort(300, $e->getMessage());
        }

        return self::getUserInform($user_id);
    }

    public function logout()
    {
        $user_id = parent::param('user_id'); //user id

        try {
            parent::database()->beginTransaction();
            $updateSql = "UPDATE `tbl_user`
                            SET
                            `last_update_time` = now(),
                            `is_login` = 0
                            WHERE `id` = :user_id";
            $stmt = parent::database()->prepare($updateSql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->execute();
            parent::database()->commit();
        } catch (\PDOException $e) {
            parent::database()->rollBack();
            return parent::abort(300, $e->getMessage());
        }

        return parent::response("success");
    }

    public function deleteAccount()
    {
        $user_id = parent::param('user_id'); //user id

        $selectSql = "SELECT photo1, photo2, photo3, photo4, photo5, photo6 FROM `tbl_user` WHERE id = :user_id";

        if ($selectSql != "") {
            $stmt = parent::database()->prepare($selectSql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($result["photo1"] != null) {
                $old_path = getcwd() . '/resources/' . $result["photo1"];
                @unlink($old_path);
            }
            if ($result["photo2"] != null) {
                $old_path = getcwd() . '/resources/' . $result["photo2"];
                @unlink($old_path);
            }
            if ($result["photo3"] != null) {
                $old_path = getcwd() . '/resources/' . $result["photo3"];
                @unlink($old_path);
            }
            if ($result["photo4"] != null) {
                $old_path = getcwd() . '/resources/' . $result["photo4"];
                @unlink($old_path);
            }
            if ($result["photo5"] != null) {
                $old_path = getcwd() . '/resources/' . $result["photo5"];
                @unlink($old_path);
            }
            if ($result["photo6"] != null) {
                $old_path = getcwd() . '/resources/' . $result["photo6"];
                @unlink($old_path);
            }
        }

        try {
            parent::database()->beginTransaction();
            $deleteSql = "DELETE from `tbl_user` WHERE `id` = :user_id";
            $stmt = parent::database()->prepare($deleteSql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->execute();
            parent::database()->commit();
        } catch (\PDOException $e) {
            parent::database()->rollBack();
            return parent::abort(300, $e->getMessage());
        }

        try {
            parent::database()->beginTransaction();
            $deleteSql = "DELETE from `tbl_blind_chat` WHERE `user_id` = :user_id";
            $stmt = parent::database()->prepare($deleteSql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->execute();
            parent::database()->commit();
        } catch (\PDOException $e) {
            parent::database()->rollBack();
            return parent::abort(300, $e->getMessage());
        }

        try {
            $selectSql = "SELECT id from `tbl_chat_list` WHERE `first_user_id` = :user_id or `second_user_id` = :user_id";
            $stmt = parent::database()->prepare($selectSql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->execute();
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            for ($i = 0; $i < count($result); $i++) {
                $chat_id = $result[$i]["id"];
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
            }
        } catch (\PDOException $e) {
            return parent::abort(300, $e->getMessage());
        }

        return parent::response("success");
    }

    public function unlimitBlind()
    {
        $user_id = parent::param('user_id'); //user id

        try {
            parent::database()->beginTransaction();
            $updateSql = "UPDATE tbl_user set is_blind_unlimited = 1
                      where id = :user_id";
            $stmt = parent::database()->prepare($updateSql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->execute();
            parent::database()->commit();
        } catch (\PDOException $e) {
            parent::database()->rollBack();
        }
    }
}
