<?php
require_once('client-id.php');
?>
<html>
<body>
<div id="fb-root"></div>
<script async defer crossorigin="anonymous" src="<?='https://connect.facebook.net/es_LA/sdk.js#xfbml=1&version=v6.0&appId='.client_id.'&autoLogAppEvents=1'?>"></script>
<div class="fb-login-button" onlogin="checkLoginState();" scope="email,instagram_basic,manage_pages,pages_show_list,instagram_manage_insights" data-width="" data-size="large" data-button-type="continue_with" data-auto-logout-link="false" data-use-continue-as="false"></div>
<script>
function checkLoginState() {
//obtiene el token temporal y lo envia a setUsuario.php
  FB.getLoginStatus(function(response) {
    if (response.status === 'connected') {
    fetch('setUsuario.php', {
        method: 'POST',
        body: response.authResponse.accessToken
    })
  }
  });
}
</script>
</body>

</html>