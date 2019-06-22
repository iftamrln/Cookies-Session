<?php

session_start();
$uname = $_POST["uname"];
$pwd = $_POST["pwd"];

if($uname=="informatika" && $pwd=="1234"){
	$_SESSION["user"] = $uname;
	header("Location: file.php");
}
else {
	echo "Username atau Password Salah";
}

?>