<?php
echo password_hash($argv[1] ?? '', PASSWORD_ARGON2ID), "\n";
