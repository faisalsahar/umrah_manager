<?php

namespace App\Services\PilgrimImport;

use RuntimeException;

/**
 * Thrown whenever an uploaded file can't be turned into a pilgrim list — a
 * corrupt/unsupported file, a blank sheet, a header row with no recognizable
 * columns, and so on. The message is written to be shown directly to the user
 * in the "incompatible file" modal, not logged as an internal error.
 */
class IncompatibleExcelFileException extends RuntimeException {}
