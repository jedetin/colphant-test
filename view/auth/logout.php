<script>
    showToast('You have been signed out.', 'info');
</script>
<?php
session_start(); /* Starts the session */
session_destroy(); /* Destroy started session */
echo "logging you out ...";
header("location:login?msg=redir3");  /* Redirect to login page */
exit;
?>