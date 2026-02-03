<?php
error_reporting(E_ERROR);

function conexion()
{
	// Credenciales correctas para scripts automáticos
	$mysqli = new mysqli('localhost', 'gestiontictaccom_usercron', ']s+tER.&k{ew(!?^', 'gestiontictaccom_admin');
	$tildes = $mysqli->query("SET NAMES 'utf8'"); //Para que se muestren las tildes correctamente

	if (mysqli_connect_errno()) {
		printf("Connect failed: %s\n", mysqli_connect_error());
		exit();
	}
	return $mysqli;
}
?>