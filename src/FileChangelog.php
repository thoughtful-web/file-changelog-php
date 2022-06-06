<?php
/**
 * File Changelog class. Records changes made to a set of files.
 *
 * @package File Changelog
 *
 * Target a list of file paths when initializing the class.
 * We can't store all of the files in memory, and we shouldn't store duplicate files either just to
 * handle a group efficiently within the class. We also need to know what will change if we commit
 * a file before we do so.
 * Ideal steps:
 * 1. Initialize the class with a list of file paths.
 * 2. Provide methods that enable per-item decision making and file C.R.U.D. operations.
 */

/**
 * The file changelog class.
 */
class File_Changelog {
	/**
	 * The contextual file label to include in messages.
	 *
	 * @var string
	 */
	protected $label;
	/**
	 * The base directory where files will be handled.
	 *
	 * @var string
	 */
	protected $basedir = '';
	/**
	 * The base URL where files will be available.
	 *
	 * @var string
	 */
	protected $baseurl = '';
	/**
	 * The history of changes made to files.
	 *
	 * @var array
	 */
	protected $history = array(
		'all'    => array(),
		'create' => array(),
		'update' => array(),
		'delete' => array(),
		'error'  => array(),
	);
	/**
	 * The initial state of the repository.
	 *
	 * @var array
	 */
	protected $initial = array();
	/**
	 * Save the last diff for performance optimization.
	 *
	 * @var array
	 */
	protected $last_diff = array();

	/**
	 * The class constructor.
	 *
	 * @param string $label The contextual type of file being handled.
	 * @param array  $base  The base directory and URL which point to the same directory on the
	 *                      server where the files will be placed.
	 * @param array  $paths The paths to files which already exist and should represent the initial
	 *                      state of the repository.
	 */
	public function __construct( string $label, array $base, $paths ){

		$this->label   = $label;
		$this->basedir = $base['dir'];
		$this->baseurl = $base['url'];
		$this->initial = $paths;

	}

	/**
	 * Get a list of differences between two files given a file path and a file content string.
	 *
	 * @param string $path     The file path.
	 * @param string $contents The file contents.
	 * @param bool   $remove   Whether the file is being removed.
	 *
	 * @return array
	 */
	public function diff( string $path, string $contents, $remove = false ) {

		$exists = $this->exists( $path );
		$diff   = array(
			'change' => false,
			'exists' => $exists,
		);
		if ( $remove && $exists ) {
			$diff['change'] = 'delete';
		} elseif ( ! $exists ) {
			$diff['change'] = 'create';
		} else {
			$is_match      = $this->is_match( $contents, $path );
			$diff['match'] = $is_match;
			if ( ! is_bool( $is_match ) ) {
				// The file read functions failed.
				$diff['match'] = false;
				$diff['error'] = 'The file could not be read to determine if it matched the content strings.';
			} elseif ( ! $is_match ) {
				$diff['change'] = 'update';
			}
		}
		// Store this result for efficiency.
		$this->last_diff = $diff;
		return $diff;

	}

	/**
	 * Get the information for a file that is stored in history.
	 *
	 * @param string $path The path to a file.
	 *
	 * @return array
	 */
	public function inspect_path( string $path ) {
		$pi = pathinfo( $path );
		$i  = array(
			'basename' => $pi['basename'],
			'ext'      => isset( $pi['extension'] ) ? $pi['extension'] : '',
			'filename' => $pi['filename'],
		);
		// Get information if a file exists.
		try {
			$i['exists'] = file_exists( $path );
		} catch ( \Exception $e ) {
			$i['exists'] = null;
		}
		if ( $i['exists'] ) {
			try {
				$i['readable'] = is_readable( $path );
			} catch ( \Exception $e ) {
				$i['readable'] = null;
			}
			try {
				$i['modified'] = filemtime( $path );
			} catch ( \Exception $e ) {
				$i['modified'] = null;
			}
			try {
				$i['filesize'] = filesize( $path );
			} catch ( \Exception $e ) {
				$i['filesize'] = null;
			}
		}

		return $i;
	}

	/**
	 * Add a file to the repository.
	 *
	 * @param string $path     The file path.
	 * @param string $contents The file contents.
	 *
	 * @return mixed
	 */
	public function add( string $path, string $contents ) {}

	/**
	 * Does string content of new file match current file.
	 *
	 * @param string $path A path to an existing file.
	 *
	 * @return boolean|Throwable
	 */
	protected function exists( $path ) {

		$exists = false;
		try {
			$exists = file_exists( $path );
		} catch ( \Throwable $e ) {
			$exists = $e;
		}
		return $exists;

	}

	/**
	 * Detects if the file path is readable.
	 *
	 * @param string $path A path to a file.
	 *
	 * @return boolean|Throwable
	 */
	protected function readable( $path ) {

		$readable = false;
		try {
			$readable = is_readable( $path );
		} catch ( \Throwable $e ) {
			$readable = $e;
		}
		return $readable;

	}

	/**
	 * Retrieves the file contents.
	 *
	 * @param string $path A path to a file.
	 *
	 * @return string|Throwable
	 */
	protected function file_get_contents( $path ) {

		$contents = '';
		try {
			$contents = file_get_contents( $path );
		} catch ( \Throwable $e ) {
			$contents = $e;
		}
		return $contents;

	}

	/**
	 * Does string content of new file match current file.
	 *
	 * @param string $suspect  The contents of a file.
	 * @param string $path     A path to an existing file.
	 *
	 * @return boolean
	 */
	protected function is_match( $suspect, $path ) {

		$readable = $this->readable( $path );
		if ( ! is_bool( $readable ) ) {
			return $readable;
		}
		$file_str = $this->file_get_contents( $path );
		if ( ! is_string( $file_str ) ) {
			return $file_str;
		}
		$b = md5( $file_str );
		$a = md5( $suspect );
		return $a === $b;

	}

	/**
	 * Return an HTML grid representation of the photo changes.
	 *
	 * @param integer $columns
	 * @return string
	 */
	public function render_grid( int $columns = 3 ) {
		return '';
	}
}
