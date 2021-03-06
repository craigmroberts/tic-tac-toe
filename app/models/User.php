<?php

  class User {

    protected $id;
    protected $name;
    protected $email;
    protected $password;
    protected $roleID;

    public static $minPasswordLength = 6;

    // Runs when object is instantiated
    public function __construct() {
      $this->id = bin2hex(openssl_random_pseudo_bytes(16)); // create random guid
      $this->roleID = '0dc97da7-29b8-4c44-9d28-c6517b2abddb';
    }

    // __get MAGIC METHOD
    public function __get($property) {
      if (property_exists($this, $property)) {
        return $this->$property;
      }
    }

    // __set MAGIC METHOD
    public function __set($property, $value) {
      if (property_exists($this, $property)) {
        if ($property === 'password') {
          $this->$property = $this->encryptString($value);;
        } else {
          $this->$property = $value;
        }
      }
      return $this->$property;
    }

    public function __desruct() {
    }

    static function getUser($id) {
      // returns a user object if id matches in db

      $obj = new stdClass();
      $obj->result = false;
      $obj->data = false;
      $obj->message = 'user not found';

      // connect to databaase
      $db = Database::getInstance();
      $connection = $db->getConnection();

      // get ststs from player_stats table
      $sql = "SELECT * FROM user INNER JOIN player_stats ON user.id=player_stats.id WHERE user.id=?";
      $stmt= $connection->prepare($sql);
      $stmt->execute([$id]);

      // use a while loop instead of fetchAll for future manipulation
      while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $arr[] = (object) $row;
      }

      $obj->result = true;
      $obj->message = 'success';
      $obj->data = $arr;

      if (count($arr) === 1) {
        // return single row as object instead of array
        $obj->data = $arr[0];
      }

      return $obj;
    }

    static private function getByEmail($data) {
      // returns a user object if email matches in db

      // connect to databaase
      $db = Database::getInstance();
      $connection = $db->getConnection();

      // get ststs from player_stats table
      $sql = "SELECT * FROM user INNER JOIN player_stats ON user.id=player_stats.id WHERE user.email=?";
      $stmt= $connection->prepare($sql);
      $stmt->execute([$data->email]);

      // use a while loop instead of fetchAll for future manipulation
      while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $arr[] = (object) $row;
      }

      if (count($arr) === 1) {
        // return single row as object instead of array
        return $arr[0];
      }

      return $arr;
    }

    static private function verifyPassword($passwordString, $usersPassword) {
      // check if the password string matches the users in the db

        // check it the password matches that users hash
        if (self::verifyEncryptedString($passwordString, $usersPassword)) {
          return true;
        }
        // incorrect password
        return false;
    }

    static public function login($data) {

      $obj = new stdClass();
      $obj->result = false;
      $obj->data = false;
      $obj->message = 'user not found';

      /*
      if (strlen($data->password) < self::$minPasswordLength) {
        $obj->message = 'password length to short';
        return $obj;
      }
      */

      $user = self::getByEmail($data);

      if ($user) {
        // user found

        // check if password is ok
        if (self::verifyPassword($data->password, $user->password)) {
          // login success
          $obj->result = true;
          $obj->data = $user;
          $obj->message = 'success';
        }

        $obj->message = 'incorrect password';
      }

      return $obj;
    }

    static public function signup($data) {

      $user = new User();

      if (strlen($data->password) < self::$minPasswordLength) {
        $obj = new stdClass();
        $obj->result = false;
        $obj->data = false;
        $obj->message = 'password length to short';
        return $obj;
      }

      $user->__set('email', $data->email);
      $user->__set('name', $data->name);
      $user->__set('password', $data->password);

      $db = Database::getInstance();
      $connection = $db->getConnection();

      $sql = "INSERT INTO user (id, email, name, password, role_id) VALUES (?, ?, ?, ?, ?)";
      $stmt= $connection->prepare($sql);
      $result = $stmt->execute([$user->id, $user->email, $user->name, $user->password, $user->roleID]);

      if ($result) {

        $sql = "INSERT INTO player_stats (id) VALUES (?)";
        $stmt= $connection->prepare($sql);
        $result = $stmt->execute([$user->id]);

        if ($result) {
          return User::login($data);
        }
      }

      return $result;
    }

    static public function signOut($user) {
      // log the user out
    }

    static public function delete($user) {

      // delete user account
      $db = Database::getInstance();
      $connection = $db->getConnection();

      // delete rows from two tables
      $sql = "DELETE user, player_stats FROM user INNER JOIN player_stats WHERE user.id=player_stats.id AND user.id=?";
      $stmt= $connection->prepare($sql);
      $result = $stmt->execute([$this->id]);

      return $result;
    }

    static private function encryptString($string) {
      $options = array(
        'cost' => 12,
      );

      $hash = password_hash($string, PASSWORD_BCRYPT, $options);

      return $hash;
    }

    static private function verifyEncryptedString($string, $hash) {
      if (password_verify($string, $hash)) {
          // Correct password
          return true;
      } else {
          // Incorrect password
          return false;
      }
    }
  }
