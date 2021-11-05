<?php

$cwd = getcwd();
chdir(__DIR__ . "/..");

$protoFiles = implode(" ", array_map("escapeshellarg", glob("proto/*.proto")));

if (PHP_OS_FAMILY === "Windows") {
	$exe = "protoc.exe";
} elseif (PHP_OS_FAMILY === "Darwin") {
	$exe = "protoc-osx";
} else {
	$exe = "protoc-x86_64";
}

exec("protoc/$exe --proto_path=proto --php_out=proto-out " . $protoFiles);

chdir($cwd);