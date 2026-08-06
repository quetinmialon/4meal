<?php

return ['expires' => (int) env('AUTH_2FA_CODE_EXPIRE', 10), 'length' => 6, 'max_attempts' => (int) env('AUTH_2FA_MAX_ATTEMPTS', 5)];
