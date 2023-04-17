# How to Setup Mastodon SSO

These instructions assume "https://nextcloud.example.com" is the base URL for your NextCloud
and "https://mastodon.example.com" is the base URL for your Mastodon. 
Adjust according to your setup.

1. Install the app (see README.md)
2. Create a developer app
    1. Go to https://mastodon.example.com/
    2. Browse to Settings -> Development
    3. Click "New Application"
    4. Enter something relevant for the name
    5. Set your "Application Website" to https://nextcloud.example.com
    6. Set your "Redirect URI" to https://nextcloud.example.com/apps/sociallogin/custom_oauth2/Mastodon
    7. Ensure at least "read" scope is provided
    8. Take note of the "Client key" and "Client secret" for this app, they will be used in NextCloud.
3. Configure "Social Login" in NextCloud
    1. Login as an admin to your NextCloud
    2. Click "Settings" in the menu
    3. Under Administration click "Social login"
    4. Create a new "Custom OAuth2" listing
    5. In the bottom section under "Facebook" enter:
       * Title: Mastodon
       * App Base URL: https://mastodon.example.com
       * Authorize url: https://mastodon.example.com/oauth/authorize
       * Token url: https://mastodon.example.com/oauth/token
       * Profile url: https://mastodon.example.com/api/v1/accounts/verify_credentials
       * Logout url: [LEAVE EMPTY]
       * Client Id: [Client ID from Mastodon]
       * Client Secret: [Client Secret from Mastodon]
       * Scope: read
       * Profile Fields: [LEAVE EMPTY]
       * Display name claim: [LEAVE EMPTY]
       * Groups claim: roles
    6. Group mappings can be added if you use Roles within Mastodon, though each Mastodon role can only be mapped to a single group in NextCloud.
    7. Click "Save" at the very bottom
5. Now, open up a new browser to test the login. On the login screen you should now see "Mastodon" underneath the typical NextCloud login prompt.

Notes, if you name the OAuth2 profile something other than "Mastodon", 
you will need to update the Redirect URI to match,
for example if you named the connection "MyFediverse", 
then the Redirect URI would be "/apps/sociallogin/custom_oauth2/MyFediverse" instead. 