<?php

session_start();
if(isset($_SESSION["user"])) {
	echo "Berhasil" . $_SESSION["user"] ."<br>";
	echo "<a href='logout.php'> Logout </a>";
}
else {
	echo "gagal";
}

?>
