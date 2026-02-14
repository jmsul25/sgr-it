<?php
session_start();
session_unset();
session_destroy();
header("Location: /sgr-it/login.php");
exit;
