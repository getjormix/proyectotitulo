<?php
$new_hash = '$2y$10$temvpTqfzweCOru1lJ8cH.MfAb9hzdP5lg1YdJUgC3Zg26hZB1ROG';
$password = '123456';

if (password_verify($password, $new_hash)) {
    echo "✅ NUEVO HASH FUNCIONA CON '123456'\n";
} else {
    echo "❌ ALGO SIGUE MAL\n";
}
