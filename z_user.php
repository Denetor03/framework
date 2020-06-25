<?php 
    /**
     * This file holds the user class
     */

    /**
     * The User class holds information about the user
     */
    class User {

        /**
         * @var bool $isLoggedIn Hold is the user is logged
         */
        public $isLoggedIn = false;

        /**
         * @var int $userId Id of the user in the database.
         * 
         * Null is equal to anonymous
         */
        public $userId = null;

        /**
         * @var int $execUserId Id of the user that is logged in as this user.
         * 
         * Null is equal to anonymous
         */
        public $execUserId = null;

        /**
         * @var array $language Holds information about the language of the user.
         * 
         * Array with the keys "id" and "value".
         */
        public $language = []; //TODO: Insert default language here

        /**
         * @var any[] $fields Holds the dataset from the database of this user
         */
        public $fields = []; //Stores custom per project properties

        /**
         * @var z_framework $booter Holds a reference to the booter
         */
        private $booter;

        /**
         * @var string[] $permissions. Array of permissions the user has.
         */
        private $permissions;

        /**
         * Creates a new user object
         * @param z_framework $booter The booter object
         */
        public function __construct($booter) {
            $this->booter = $booter;
        }

        /**
         * Checks if the user is someone registered. Checks by the z_login_token cookie. 
         * The property $isLoggedIn and $userId and $execUserId will be set after calling this function.
         */
        public function identify() {
            if ($this->booter->lite_mode || !isset($_COOKIE["z_login_token"]) || empty($_COOKIE["z_login_token"])) {
                $this->anonymousRequest();
            }

            $tokenResult = $this->booter->getModel("z_login")->validateCookie($_COOKIE["z_login_token"]);
            if(!isset($tokenResult["userId"]) || !isset($tokenResult["userId_exec"])) {
                $this->anonymousRequest();
            }
            $this->userId = $tokenResult["userId"];
            $this->execUserId = $tokenResult["userId_exec"];

            if ($this->userId !== false) {
                $user = $this->booter->getModel("z_user")->getUserById($this->userId);
                if ($user !== false) {
                    $this->isLoggedIn = true;
                    $this->language["id"] = $user["languageId"];
                    $this->language["value"] = $this->booter->getModel("z_general")->getLanguageById($this->language["id"])["value"];
                    $this->fields = $user;
                }
            }
        }

        private function anonymousRequest() {
            $this->isLoggedIn = false;
            $this->chooseNonLoginLanguage();
            return;
        }

        private function chooseNonLoginLanguage() {
            if(empty($this->language) && !in_array($this->booter->settings["anonymous_language"], ["", " ", "  ", "\t"])) {
                $lang;
                if(isset($_COOKIE["z_lang"]) && !isset($_GET["lang"])){
                    $lang = $_COOKIE["z_lang"]; 
                } else {
                    $default = str_replace(" ", "", $this->booter->settings["anonymous_language"]);
                    $lang = isset($_GET["lang"]) && strlen($_GET["lang"]) == 2 ? $_GET["lang"] : (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) : $default);
                    
                    $availableLang = []; 
                    foreach (explode(",", str_replace(" ", "", $this->booter->settings["anonymous_available_languages"])) as $langCode) {
                        $availableLang[] = $langCode;
                    }
                    $lang = in_array($lang, $availableLang) ? $lang : $default;
                    
                    setcookie("z_lang", $lang);
                }
                if($this->booter->lite_mode) {
                    $this->language = [
                        "value" => $lang
                    ];
                } else {
                    $this->language = [
                        "value" => $lang,
                        "id" => $this->booter->getModel("z_general")->getLanguageByValue($lang)
                    ];
                }
            } else {
                $this->language = [
                    "value" => "EN",
                    "id" => 0
                ];
            }
        }

        /**
         * Checks if a user has a given permission
         * @param string $permission Name of the permission
         * @return bool True when the permission is given
         */
        public function checkPermission($permission) {
            if (!isset($this->permissions)) {
                $this->permissions = $this->booter->getModel("z_user")->getPermissionsByUserId($this->userId);
            }
            $parts = explode(".", $permission);

            $perm = "";
            $toCheck = ["*.*", $permission];

            foreach ($parts as $part) {
                $perm .= $part . ".";
                $toCheck[] = $perm . "*";
            }
            
            foreach ($toCheck as $check) {
                if (in_array($check, $this->permissions)) return true;
            }
            return false;
        }

    }
?>