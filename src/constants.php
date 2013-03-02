<?php
/**
 * Few useful global constants used by SplotFramework.
 * 
 * @package SplotFramework
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */

// These constants define possible environments
defined('SplotEnv_Production') or define('SplotEnv_Production', 'production');
defined('SplotEnv_Staging') or define('SplotEnv_Staging', 'staging');
defined('SplotEnv_Dev') or define('SplotEnv_Dev', 'dev');

/*
define('SplotValidationError_Empty', 'notFilled');
define('SplotValidationError_NotFilled', 'notFilled');
define('SplotValidationError_TooLong', 'tooLong');
define('SplotValidationError_TooShort', 'tooShort');
define('SplotValidationError_InvalidCharacters', 'invalidChars');
define('SplotValidationError_Invalid', 'invalid');
define('SplotValidationError_AlreadyExists', 'alreadyExists');

define('SplotFileError_TooBig', 1);
define('SplotFileError_NotFound', 2);
define('SplotFileError_Partial', 3);
define('SplotFileError_NoneUploaded', 4);
define('SplotFileError_WrongFileType', 5);

define('SplotError403', 403);
define('SplotError404', 404);
define('SplotError500', 500);
define('SplotError_NoAccess', SplotError403);
define('SplotError_Forbidden', SplotError403);
define('SplotError_NoPage', SplotError404);
define('SplotError_NotFound', SplotError404);
define('SplotError_Server', SplotError500);

define('SplotNotNull', 'NOT NULL');
define('SplotIsNull', 'NULL');
define('SplotOrderAscending', 'ASC');
define('SplotOrderDescending', 'DESC');
define('SplotAscendingOrder', SplotOrderAscending);
define('SplotDescendingOrder', SplotOrderDescending);
define('SplotLogicOr', 'OR');
define('SplotLogicAnd', 'AND');
*/