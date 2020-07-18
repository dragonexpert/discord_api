# discord_api
This is a discord class to make integration easy with your website.  Requires PHP 7.2 or higher.  Most calls should be stable.  If any calls are not stable / don't work, create an issue.

## usage
Include the file in your application.  Create an instance of the discord class passing the client id, client secret, and redirect url to it.  Once you obtain an access token, use the set_access_token($access_token) method to use for future calls.  It is important to note that this library does not check if the user has permission to perform the request sent.  The developer can use the get_guild_member($guild_id, $discordid) method to determine what permissions a user has and store them.
