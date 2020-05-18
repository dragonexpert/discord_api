<?php

/**
 * @Id: class_discord.php
 * @Purpose: Provide a class with easy methods to perform Discord integrations.
 * @Author: Mark Janssen
 * @Licence: This file may be modified beyond setting up basic configuration information to your specific needs without permission.
 * You may not redistribute any changes made to the file. If you have suggestions, please raise an issue on Github.  You are allowed
 * to convert this code to a different programming language such as Python and submit a Pull Request on Github.
 * @Donations: https://paypal.me/MarkJanssen
 */
class discord
{
    /**
     * @var string The client id for discord
     */
    private $client_id;

    /**
     * @var string The client secret for discord
     */
    private $client_secret;

    /**
     * @var string The url to redirect to after getting a code.
     */
    private $redirect_uri;

    /**
     * @var string The access token for the current user.
     */
    private $access_token;

    /**
     * @var string The token for a bot.
     */
    private $bot_token;

    /**
     * @var string THe url to discord api.
     */
    public $discord_api = "https://discordapp.com/api";

    /**
     * @var string The url to send a token request to given the code
     */
    public $token_uri = "https://discordapp.com/api/oauth2/token";

    /**
     * @var string The url to get an implicit grant.
     */
    public $implicit_grant_uri = "https://discord.com/api/oauth2/authorize?response_type=token";

    /**
     * @var string The base url for bots.
     */
    public $bot_uri = "https://discord.com/api/oauth2/authorize";

    /**
     * @var string[] The list of scopes that are valid for discord.  Note that some require being whitelisted.
     */
    public $valid_scopes = array("bot", "connections", "email", "identify", "guilds", "guilds.join", "gdm.join", "messages.read",
        "rpc", "rpc.api", "rpc.notifications.read", "webhook.incoming", "applications.builds.upload",
        "applications.builds.read", "applications.store.update", "applications.entitlements", "relationships.read",
        "activities.read", "activities.write");


    /**
     * discord constructor.
     * @param int $client_id The client id of the application.
     * @param string $client_secret The client secret of the application.
     * @param string $redirect_uri The default redirection url for logins.
     * @param string $bot_token The token for the bot.
     */
    public function __construct(int $client_id, string $client_secret, string $redirect_uri, string $bot_token)
    {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->redirect_uri = $redirect_uri;
        $this->bot_token = $bot_token;
    }

    /**
     * This function sets the access token for calls where it is needed.
     * @param string $access_token The access token to set
     */
    public function set_access_token(string $access_token)
    {
        $this->access_token = $access_token;
    }

    /**
     * This function generates a login url. It uses the redirect_uri value specified above.
     * @param mixed $scopes Either an array of scopes or a comma separated list of scopes.
     * @param string $login_text The text for the link to display. HTML supported.
     * @param string $state Optional used for CSRF protection.
     * @return string The full login link or an error if there are no valid scopes requested.
     */
    public function generate_login_uri($scopes, string $login_text = "Login", string $state = "")
    {
        $login_uri = "https://discordapp.com/api/oauth2/authorize?client_id=" . $this->client_id .
            "&redirect_uri=" . urlencode($this->redirect_uri) . "&response_type=code&scope=";
        $separator = "";
        $no_valid_scope = true;
        $scopelist = $this->scope_handler($scopes);
        if (!$scopelist)
        {
            return "No valid scopes were defined.";
        }
        $login_uri .= $scopelist;
        // Check for the existence of a state parameter and encode it if it is given.
        if ($state)
        {
            $login_uri .= "&state=" . urlencode($state);
        }
        return "<a href='" . $login_uri . "'>" . $login_text . "</a>";
    }

    /**
     * @param string $scope The scope to check if it is valid
     * @return bool Returns true if the scope is valid and false if it is not.
     * It should be noted that some scopes require being on a whitelist to use them.
     */
    public function is_valid_scope(string $scope)
    {
        return in_array($scope, $this->valid_scopes);
    }

    /**
     * @return string The list of scopes supported by Discord.  Some require being whitelisted.
     */
    public function list_valid_scopes()
    {
        $comma = "";
        $scopelist = "";
        foreach ($this->valid_scopes as $scope)
        {
            $scopelist .= $comma . $scope;
            $comma = ", ";
        }
        return $scopelist;
    }

    /**
     * @param string $code The code obtained by exchanging the client id.
     * @param string $redirect_uri The url to redirect to.
     * @return mixed On success: an access token object, On Failure: an object with error information.
     */
    public function login_request(string $code, string $redirect_uri = "")
    {
        if (!$redirect_uri)
        {
            $redirect_uri = $this->redirect_uri;
        }
        $token = curl_init();
        curl_setopt_array($token, array(
            CURLOPT_URL => $this->token_uri,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => array(
                "grant_type" => "authorization_code",
                "client_id" => $this->client_id,
                "client_secret" => $this->client_secret,
                "redirect_uri" => $redirect_uri,
                "code" => $code
            )
        ));
        curl_setopt($token, CURLOPT_RETURNTRANSFER, true);
        $resp = json_decode(curl_exec($token));
        curl_close($token);
        return $resp;
    }

    /**
     * @param mixed $scopes An array or comma separated value of scopes the token has.
     * @param string $refresh_token The refresh token to use.
     * @return mixed a refresh token object.
     */
    public function refresh_access_token($scopes, string $refresh_token)
    {
        $token_uri = "https://discord.com/api/oauth2/token";
        $scopelist = str_replace("%20", " ", $this->scope_handler($scopes));
        $token = curl_init();
        curl_setopt_array($token, array(
            CURLOPT_URL => $this->token_uri,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => array(
                "grant_type" => "refresh_token",
                "refresh_token" => $refresh_token,
                "client_id" => $this->client_id,
                "client_secret" => $this->client_secret,
                "redirect_uri" => $this->redirect_uri,
                "scope" => $scopelist
            )
        ));
        curl_setopt($token, CURLOPT_RETURNTRANSFER, true);
        return json_decode(curl_exec($token));
    }

    /**
     * This function revokes an access token.
     * @param string $access_token The token to revoke.
     */
    public function revoke_access_token(string $access_token)
    {
        $revoke_uri = $this->token_uri . "/revoke";
        $token = curl_init();
        curl_setopt_array($token, array(
            CURLOPT_URL => $revoke_uri,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => array(
                "access_token" => $access_token,
                "client_id" => $this->client_id,
                "client_secret" => $this->client_secret,
                "redirect_uri" => $this->redirect_uri
            )
        ));
        curl_setopt($token, CURLOPT_RETURNTRANSFER, true);
        curl_exec($token);
    }

    /**
     * @return mixed Information about the access token.
     */
    public function check_access_token()
    {
        $info_request = "https://discordapp.com/api/oauth2/@me";
        $info = curl_init();
        curl_setopt_array($info, array(
            CURLOPT_URL => $info_request,
            CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer {$this->access_token}"
            ),
            CURLOPT_RETURNTRANSFER => true
        ));

        $user = json_decode(curl_exec($info));
        curl_close($info);
        return $user;
    }

    /**
     * This function returns an url to get an implicit grant. The redirect will have the following parameters as fragments:
     * access_token, token_type, expires_in, scope, and state if specified.  No refresh token is available.
     * @param mixed $scopes Either an array or comma separate value of scopes.
     * @param string $state Optional. Used to prevent CSRF when provided.
     * @return string The url for the implicit grant.
     */
    public function implicit_grant($scopes, string $state = "")
    {
        $scopelist = $this->scope_handler($scopes);
        $implicit_uri = $this->implicit_grant_uri . "&client_id=" . $this->client_id;
        if ($state)
        {
            $implicit_uri .= "&state=" . urlencode($state);
        }
        $implicit_uri .= "&scope=" . $scopelist;
        return $implicit_uri;
    }

    /**
     * This function grants client credentials and should only be used for testing.
     * @param mixed $scopes Either an array or CSV of scopes.
     * @return mixed An access token object.  Fields: access_token, token_type, expires_in, scope.  No refresh token is available.
     */
    public function client_credentials_grant($scopes)
    {
        $scopelist = $this->scope_handler($scopes);
        $token = curl_init();
        curl_setopt_array($token, array(
            CURLOPT_URL => $this->token_uri,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => array(
                "grant_type" => "client_credentials",
                "client_id" => $this->client_id,
                "client_secret" => $this->client_secret,
                "redirect_uri" => $this->redirect_uri,
                "scope" => $scopelist
            )
        ));
        curl_setopt($token, CURLOPT_RETURNTRANSFER, true);
        return json_decode(curl_exec($token));
    }

    /**
     * This function generates an url to add a bot to a guild.
     * @param mixed $scopes Either an array or CSV of scopes requested. Bot should be included.
     * @param int $permissions An integer of permissions requested. Use the discord developer to figure out the value requested. 8 is an administrator.
     * @param int $guild_id When a valid guild id is specified, that will be automatically selected.
     * @param bool $disable_guild_select When set to true, denies the option to send the bot to a different guild. Generally used with guild id set.
     * @return string Either the url on success or an error message on failure.
     */
    public function generate_bot_authorize_url($scopes, int $permissions = 0, int $guild_id = 0, bool $disable_guild_select = false)
    {
        $scopelist = $this->scope_handler($scopes);
        $bot_uri = $this->bot_uri . "?client_id=" . $this->client_id;
        if (!$scopelist)
        {
            return "No valid scopes defined.";
        }
        $bot_uri .= "&scope=" . $scopelist;
        if (!$permissions)
        {
            return "Permissions must be greater than 0.";
        }
        $bot_uri .= "&permissions=" . $permissions;
        if ($guild_id)
        {
            $bot_uri .= "&guild_id=" . $guild_id;
        }
        if ($disable_guild_select)
        {
            $bot_uri .= "&disable_guild_select=" . $disable_guild_select;
        }
        return $bot_uri;
    }

    /**
     * This function generates a webhook url. After clicking the link, the user should have the code exchanged for an access token.  Upon
     * getting a response, you should store webhook.id and webhook.token.
     * @param string $state Optional used for CSRF protection.
     * @param string $redirect_url If specified, the url to redirect to.  Default is the main redirect url.
     * @return string The url to navigate to.
     */
    public function generate_webhook_url(string $state = "", string $redirect_url = "")
    {
        $webhook_uri = $this->bot_uri . "?response_type=code&client_id=" . $this->client_id . "&scope=webhook.incoming";
        if ($state)
        {
            $webhook_uri .= "&state=" . $state;
        }
        if (!$redirect_url)
        {
            $redirect_url = $this->redirect_uri;
        }
        $webhook_uri .= "&redirect_uri=" . urlencode($redirect_url);
        return $webhook_uri;
    }

    /**
     * This function enables you to get information about a user.
     * @param string $access_token The access token that was issued.
     * @return mixed a user object or an error object.
     */
    public function get_user_data(string $access_token)
    {
        if ($access_token)
        {
            $info_request = "https://discordapp.com/api/users/@me";
            $info = curl_init();
            curl_setopt_array($info, array(
                CURLOPT_URL => $info_request,
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Bearer {$access_token}"
                ),
                CURLOPT_RETURNTRANSFER => true
            ));

            $user = json_decode(curl_exec($info));
            curl_close($info);
            return $user;
        }
    }

    /**
     * This function generates a post request to a webhook.
     * @param int $id The id of the webhook retrieved from clicking on the webhook url.
     * @param string $token The token of the webhook retrieved from clicking on the webhook url.
     */
    public function generate_webhook_request(int $id, string $token)
    {
        $webhook_uri = "https://discord.com/api/webhooks/" . $id . "/" . $token;
        $webhook = curl_init();
        curl_setopt_array($webhook, array(
            CURLOPT_URL => $webhook_uri,
            CURLOPT_POST => 1,
        ));
        curl_setopt($webhook, CURLOPT_RETURNTRANSFER, true);
        curl_exec($webhook);
    }

    /**
     * This function returns information about the current application.
     * @return mixed An object containing application information on success or an object containing error message on failure.
     */
    public function get_current_application_information()
    {
        $application_uri = "https://discordapp.com/api/oauth2/applications/@me";
        $ch = curl_init($application_uri);
        $resp = json_decode(curl_exec($ch));
        curl_close($ch);
        return $resp;
    }

    /**
     * @param int $discordid The discord id of the user.
     * @return mixed An object of user info.
     */
    public function get_user(int $discordid = 0)
    {
        return $this->get_request("users", $discordid);
    }

    /**
     * This function gets a list of user guilds as objects.
     * @return mixed An object with all guilds a user is in.
     */
    public function get_user_guilds()
    {
        return $this->get_request("users", "@me/guilds", true);
    }

    /**
     * This function returns a guild object.
     * @param int $guild_id The id of the guild.
     * @param bool $with_count Whether to get an estimate of guild members.
     * @return mixed A guild object.
     */
    public function get_guild(int $guild_id = 0, bool $with_count = false)
    {
        return $this->get_request("guilds", $guild_id . "?with_counts=" . $with_count, true, true);
    }

    /**
     * This function can only be used by bots that are in less than 10 guilds.
     * @param array $data An array in $key => $value pairs.  The key name is required, anything else is optional.
     * @return mixed|string A guild object on success.  An error object on failure.
     */
    public function create_guild($data = array())
    {
        if (!array_key_exists("name", $data))
        {
            return "The key name is required for creating a guild.";
        }
        return $this->post_request("guilds", 0, "", $data);
    }

    /**
     * This function gets a preview of a guild.  Only works for public guilds.
     * @param int $guild_id The id of the guild.
     * @return mixed A guild preview object.
     */
    public function get_guild_preview(int $guild_id = 0)
    {
        return $this->get_request("guilds", $guild_id . "/preview", true, true);
    }

    /**
     * This function gets guild channels.
     * @param int $guild_id The id of the guild.
     * @return mixed A guild channel object.
     */
    public function get_guild_channels(int $guild_id = 0)
    {
        return $this->get_request("guilds", $guild_id . "/channels");
    }

    /**
     * This function creates a guild channel.  Requires the Manage Channels permission.
     * @param int $guild_id The id of the guild.
     * @param array $data An array of $key => $value pairs.  Name is the only required field.
     * @return mixed|string An object containing the data of the created channel on success. Error object on failure.
     */
    public function create_guild_channel(int $guild_id = 0, $data = array())
    {
        if (!array_key_exists("name", $data))
        {
            return "The key name is required to be present for creating a guild channel.";
        }
        return $this->post_request("guilds", $guild_id, "channels", $data);
    }

    /**
     * This function gets a guild member.
     * @param int $guild_id The id of the guild.
     * @param int $discordid The discord id of the user.
     * @return mixed A guild member object on success.  An error object on failure.
     */
    public function get_guild_member(int $guild_id = 0, int $discordid = 0)
    {
        return $this->get_request("guilds/" . $guild_id . "/members", $discordid, true, true);
    }
    /**
     * This function lists all guild members.
     * @param int $guild_id The id of the guild.
     * @param int $after The highest id of the last guild member returned.
     * @return mixed Guild member object on success.  An error object on failure.
     */
    public function list_guild_members(int $guild_id = 0, int $after = 0)
    {
        return $this->get_request("guilds", $guild_id . "/members?after=" . $after);
    }

    /**
     * This function lists all the guild bans.  Requires the BAN_MEMBERS permission.
     * @param int $guild_id The id of the guild.
     * @return mixed A list of guild ban objects on success.  An error object on failure.
     */
    public function get_guild_bans(int $guild_id = 0)
    {
        return $this->get_request("guilds", $guild_id . "/bans", true);
    }

    /**
     * This function gives information about a specific ban.  Requires the BAN_MEMBERS permission.
     * @param int $guild_id The id of the guild.
     * @param int $discordid The id of the user.
     * @return mixed A ban object on success.  An error object on failure.
     */
    public function get_guild_ban(int $guild_id = 0, int $discordid = 0)
    {
        return $this->get_request("guilds/" . $guild_id . "/bans", $discordid, true);
    }

    /**
     * This function is used for getting all roles in a guild.
     * @param int $guild_id The id of the guild.
     * @return mixed A llist of guild role objects on success.  An error object on failure.
     */
    public function get_guild_roles(int $guild_id = 0)
    {
        return $this->get_request("guilds", $guild_id . "/roles", true, true);
    }

    /**
     * This function creates a new role.  Requires the MANAGE_ROLES permission.
     * @param int $guild_id The id of the guild.
     * @param array $data An array of $key => $value pairs.  The key name is required.
     * @return mixed|string A role object on success.  An error object on failure.
     */
    public function create_guild_role(int $guild_id = 0, $data = array())
    {
        if (!array_key_exists("name", $data))
        {
            return "The key name is required for creating a guild role.";
        }
        return $this->post_request("guilds", $guild_id, "roles", $data, true);
    }

    /**
     * This function adds a user to the specified role.
     * @param int $guild_id The id of the guild.
     * @param int $user_id The id of the user.
     * @param int $role_id The id of the role.
     * @return mixed Data about the request.  Error object on failure.
     */
    public function add_user_to_role(int $guild_id=0, int $user_id=0, int $role_id=0)
    {
        $url = $this->discord_api . "/guilds/" . $guild_id . "/members/" . $user_id . "/roles/" . $role_id;
        return $this->put_request($url, true);
    }

    /**
     * This function removes a role from a user.
     * @param int $guild_id The id of the guild.
     * @param int $user_id The id of the user.
     * @param int $role_id The id of the role.
     * @return mixed Data about the request.  Error object on failure.
     */
    public function delete_user_from_role(int $guild_id=0, int $user_id=0, int $role_id=0)
    {
        $url = $this->discord_api . "/guilds/" . $guild_id . "/members/" . $user_id . "/roles/" . $role_id;
        return $this->delete_request($url, true);
    }

    /**
     * This function gets a list of guild invites.  Requires the MANAGE_GUILD permission.
     * @param int $guild_id The id of the guild.
     * @return mixed A list of invite objects on success.  An error object on failure.
     */
    public function get_guild_invites(int $guild_id = 0)
    {
        return $this->get_request("guilds", $guild_id . "/invites", true);
    }

    /**
     * This function gets a list of guild integrations. Requires the MANAGE_GUILD permission.
     * @param int $guild_id The id of the guild.
     * @return mixed A list of integration objects on success.  An error object on failure.
     */
    public function get_guild_integrations(int $guild_id = 0)
    {
        return $this->get_request("guilds", $guild_id . "/integrations", true);
    }

    /**
     * This function creates a guild integration.  Requires the MANAGE_GUILD permission.
     * @param int $guild_id The id of the guild.
     * @param array $data An array of $key => $value pairs.
     * @return mixed|string An integration object on success.  An error object on failure.
     */
    public function create_guild_integration(int $guild_id = 0, $data = array())
    {
        if (!array_key_exists("type", $data) || !array_key_exists("id", $data))
        {
            return "Create a guild integration requires the keys type and id.";
        }
        return $this->post_request("/guilds", $guild_id, "integrations", $data);
    }

    /**
     * This function gets a guild widget.  Requires the MANAGE_GUILD permission.
     * @param int $guild_id The id of the guild.
     * @return mixed A widget object on success.  An error object on failure.
     */
    public function get_guild_widget(int $guild_id = 0)
    {
        return $this->get_request("guilds", $guild_id . "/widget", true);
    }

    /**
     * This function gets an image of the guild.
     * @param int $guild_id The id of the guild.
     * @param string $style The style of image.  Valid values are shield, banner1, banner2, banner3, and banner4.
     * @return mixed
     */
    public function get_guild_widget_image(int $guild_id = 0, string $style = "shield")
    {
        return $this->get_request("guilds", $guild_id . "/widget.png", true,true);
    }

    /**
     * This function gets a vanity url.  Requires the MANAGE_GUILD permission.
     * @param int $guild_id The id of the guild.
     * @return mixed A partial invite object on success.  An error object on failure.
     */
    public function get_guild_vanity_url(int $guild_id = 0)
    {
        return $this->get_request("guilds", $guild_id . "/vanity-url", true);
    }

    // Now the guild channel resource.

    /**
     * This function gets a guild channel by id.
     * @param int $channel_id The id of the channel.
     * @return mixed A channel object on success.  An error object on failure.
     */
    public function get_channel(int $channel_id = 0)
    {
        return $this->get_request("channels", $channel_id, true, true);
    }

    /**
     * This function gets channel messages.  Requires the VIEW_MESSAGES permission if used on a guild channel.  Must have
     * READ_MESSAGE_HISTORY permission or this will return no messages.
     * @param int $channel_id The id of the channel.
     * @param string $parameter_name The name of a parameter if searching.
     * @param int $parameter_value The value of the parameter being used.
     * @param int $limit The number of messages to return.
     * @return mixed|string A list of message objects on success.  An error object on failure.
     */
    public function get_channel_messages(int $channel_id = 0, string $parameter_name = "around", int $parameter_value = 0, int $limit = 50)
    {
        $valid_parameters = array("after", "before", "around");
        if (!in_array($parameter_name, $valid_parameters) && $parameter_name != "")
        {
            return "Argument 2 must be one of after, before, or around.";
        }
        $parameter_part = "";
        if ($parameter_name)
        {
            $parameter_part = "&" . $parameter_name . "=" . $parameter_value;
        }
        return $this->get_request("channels", $channel_id . "/messages?limit=" . $limit . $parameter_part, true, true);
    }

    /**
     * This function gets channel invites.  Requires the MANAGE_CHANNELS permission.
     * @param int $channel_id The id of the channel.
     * @return mixed A list of invite objects on success.  An error object on failure.
     */
    public function get_channel_invites(int $channel_id = 0)
    {
        return $this->get_request("channels", $channel_id . "/invites", true);
    }

    /**
     * This function lets you invite a person to a channel.  Requires the CREATE_INSTANT_INVITE permission.
     * @param int $channel_id The id of the channel.
     * @param array $data An array of $key => $value pairs.
     * @return mixed An invite object on success.  An error object onn failure.
     */
    public function create_channel_invite(int $channel_id, $data = array())
    {
        return $this->post_request("channels", $channel_id, "invites", $data);
    }

    /**
     * This functio ngets a list of channel pinned messages.
     * @param int $channel_id The id of the channel.
     * @return mixed A list of message objects on success.  An error object on failure.
     */
    public function get_channel_pins(int $channel_id = 0)
    {
        return $this->get_request("channels", $channel_id . "/pins");
    }

    /**
     * This function performs a simple get request.
     * @param string $endpoint A discord endpoint
     * @param mixed $id The id of the resource.
     * @param bool $requires_auth Whether authorization is required for the request.
     * @param bool $bot_token Whether a bot token is used.
     * @return mixed An object of the data.
     */
    public function get_request(string $endpoint, $id = 0, bool $requires_auth = false, bool $bot_token = false)
    {
        $url = $this->discord_api . "/" . $endpoint . "/" . $id;
        $ch = curl_init();
        if($requires_auth)
        {
            if(!$bot_token)
            {
                curl_setopt_array($ch, array(
                    CURLOPT_URL => $url,
                    CURLOPT_HTTPHEADER => array(
                        "Authorization: Bearer {$this->access_token}"
                    ),
                    CURLOPT_RETURNTRANSFER => true));
            }
            else
            {
                curl_setopt_array($ch, array(
                    CURLOPT_URL => $url,
                    CURLOPT_HTTPHEADER => array(
                        "Authorization: Bot {$this->bot_token}"
                    ),
                    CURLOPT_RETURNTRANSFER => true));
            }
        }

      /*  if ($requires_auth)
        {
            if(!$bot_token)
            {
                curl_setopt($ch, CURLOPT_HTTPHEADER, "Authorization: Bearer " . $this->access_token);
            }
            else
            {
                curl_setopt($ch, CURLOPT_HTTPHEADER, "Authorization: Bot " . $this->bot_token);
            }
        } */
        $resp = json_decode(curl_exec($ch));
        curl_close($ch);
        return $resp;
    }

    /**
     * This function executes a post request.
     * @param string $endpoint The API endpoint to hit.
     * @param mixed $id When not 0, the id required.
     * @param string $point2 If a second endpoint is required.
     * @param array $data An array of data containing key => value pairs.
     * @param bool $bot_token Whether a bot token is used.
     * @return mixed An object of the requested type.
     */
    public function post_request(string $endpoint, $id = 0, string $point2 = "", $data = array(), bool $bot_token=false)
    {
        $url = $this->discord_api . "/" . $endpoint;
        if($bot_token)
        {
            $token = $this->bot_token;
            $headertype = "Bot ";
        }
        else
        {
            $token = $this->access_token;
            $headertype = "Bearer";
        }
        $url = $this->discord_api . "/guilds";
        $token = $this->bot_token;
        $headertype = "Bot ";
        if ($id)
        {
            $url .= "/" . $id;
        }
        if($point2)
        {
            $url .= "/" . $point2;
        }
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => array("Authorization: Bot {$token}",
                "Content-type: application/json"),
            CURLOPT_POST => 1,
            CURLOPT_RETURNTRANSFER => true,
        ));
        // It is necessary to encode the data to JSON or requests ignore parameters.
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $resp = json_decode(curl_exec($ch));
        curl_close($ch);
        return $resp;
    }

    /**
     * This function performs a put request.
     * For a PUT request it is required to have a content-length header set and the value 0 if you are not posting data.
     * @param string $url The full url.
     * @param bool $bot_token Whether to use a bot token.
     * @return mixed Object with request information.
     */
    public function put_request(string $url, bool $bot_token=false)
    {
        if(!$bot_token)
        {
            $headertype = "Bearer";
            $token = $this->access_token;
        }
        else
        {
            $headertype = "Bot";
            $token = $this->bot_token;
        }
        $ch = curl_init();
        curl_setopt_array($ch,
            array(CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "PUT",
                CURLOPT_HTTPHEADER => array(
                    "Authorization: {$headertype} {$token}",
                    "Content-length: 0"
                )
            ));
        $resp = json_decode(curl_exec($ch));
        $resp->data = curl_getinfo($ch);
        curl_close($ch);
        return $resp;
    }

    /**
     * This function performs a delete request.
     * @param string $url The full url.
     * @param bool $bot_token Whether to use a bot token.
     * @return mixed Object with request information.
     */
    public function delete_request(string $url, bool $bot_token = false)
    {
        if(!$bot_token)
        {
            $headertype = "Bearer";
            $token = $this->access_token;
        }
        else
        {
            $headertype = "Bot";
            $token = $this->bot_token;
        }
        $ch = curl_init();
        curl_setopt_array($ch,
            array(CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "DELETE",
                CURLOPT_HTTPHEADER => array(
                    "Authorization: {$headertype} {$token}",
                    "Content-length: 0"
                )
            ));
        $resp = json_decode(curl_exec($ch));
        $resp->data = curl_getinfo($ch);
        curl_close($ch);
        return $resp;
    }

    /**
     * This function takes $scopes as either an array or comma separated values list and verifies what scopes are valid and formats them.
     * @param mixed $scopes Array or CSV of scopes.
     * @return string The string to use for a request.
     */
    private function scope_handler($scopes)
    {
        $scopelist = $separator = "";
        if (is_array($scopes))
        {
            foreach ($scopes as $scope)
            {
                if ($this->is_valid_scope($scope))
                {
                    $scopelist .= $separator . $scope;
                    $separator = "%20";
                }
            }
        }
        else
        {
            $scopes = explode(",", $scopes);
            foreach ($scopes as $scope)
            {
                if ($this->is_valid_scope($scope))
                {
                    $scopelist .= $separator . $scope;
                    $separator = "%20";
                }
            }
        }
        return $scopelist;
    }
}
