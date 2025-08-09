<?php
session_start();
session_unset();
session_destroy();
header("Location: /clinic-app/admin"); // Redirect to admin login or homepage
exit();
