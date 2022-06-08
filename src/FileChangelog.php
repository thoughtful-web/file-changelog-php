<?php
/**
 * File Changelog class. Records changes made to a set of files.
 *
 * @package Thoughtful Web
 *
 * Target a list of file paths when initializing the class.
 * We can't store all of the files in memory, and we shouldn't store duplicate files either just to
 * handle a group efficiently within the class. We also need to know what will change if we commit
 * a file before we do so.
 * Ideal steps:
 * 1. Initialize the class with a list of file paths.
 * 2. Loop over and commit files.
 *    a. Create a diff using a path and (maybe new) contents
 *    b. If the diff indicates the files are identical or the new file is in an error state,
 *       do not proceed.
 *    c. Otherwise proceed with applying the change.
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
	 * Store the last generated diff for performance.
	 *
	 * @var array
	 */
	protected $last_diff;

	/**
	 * The history of changes made to files.
	 *
	 * @var array
	 */
	protected $changes = array(
		'create' => array(),
		'update' => array(),
		'delete' => array(),
		'error'  => array(),
		'none'   => array(),
	);

	/**
	 * The initial state of the repository.
	 *
	 * @var array
	 */
	protected $initial = array();

	/**
	 * The class constructor.
	 *
	 * @param string   $label The contextual type of file being handled.
	 * @param array    $base  The base directory and URL which point to the same directory on the
	 *                        server where the files will be placed.
	 * @param string[] $paths The paths to files which already exist and should represent the
	 *                        initial state of the repository.
	 */
	public function __construct( string $label, array $base, array $paths = array() ){

		$this->label   = $label;
		$this->basedir = $base['dir'];
		$this->baseurl = $base['url'];
		foreach ( $paths as $path ) {
			$key                   = basename( $path );
			$this->initial[ $key ] = $this->inspect_path( $path );
		}

	}

	/**
	 * Get a list of differences between two files given a file path and a file content string.
	 * Possible return values for 'change' are false, 'delete', 'create', or 'update'.
	 *
	 * @param string $path    The file path.
	 * @param string $content The file contents. If empty the file is being removed.
	 *
	 * @return array
	 */
	public function diff( string $path, string $content = '' ) {

		$info = $this->inspect_path( $path );
		// This case should never happen and is an indication of incorrect use.
		if ( ! $info['exists'] && ! $content ) {
			return array();
		}
		// Declare the default diff.
		$diff = array(
			'path'   => $path,
			'change' => false,
			'exists' => $info['exists'],
			'match'  => false,
			'error'  => false,
			'staged' => false,
			'size'   => array(
				'before' => $info['filesize'],
			),
		);
		// Detect changes.
		if ( ! $content ) {
			$diff['change'] = $info['exists'] ? 'delete' : false;
		} elseif ( ! $info['exists'] ) {
			$diff['change'] = 'create';
		} else {
			$is_match      = $this->is_match( $content, $path );
			$diff['match'] = $is_match;
			if ( ! is_bool( $is_match ) ) {
				// The file read functions failed.
				$diff['match'] = null;
				$diff['error'] = 'The file could not be read to determine if it matched the content strings.';
			} elseif ( ! $is_match ) {
				$diff['change'] = 'update';
			}
		}
		$this->last_diff = $diff;
		return $diff;

	}

	/**
	 * Commit changes to the file system.
	 *
	 * @param string $path    The file path.
	 * @param string $content The new file contents.
	 *
	 * @return mixed
	 */
	public function commit( string $path, string $content ) {
		// Get the diff.
		$diff = $this->last_diff && $this->last_diff['path'] === $path ? $this->last_diff : $this->diff( $path, $content );
		// Detect error case.
		if ( ! $diff['change'] || $diff['error'] ) {
			$this->last_diff = array();
			return $diff;
		}
		// Continue with uploading the new contents to the path.
		if ( 'delete' === $diff['change'] ) {
			unlink( $path );
		} else {
			$result = $this->put_content( $path, $content );
			if ( false !== $result ) {
				$diff['size']['after']  = $result;
				$diff['size']['change'] = $diff['size']['before'] - $diff['size']['after'];
			} else {
				$diff['error'] = true;
				return $diff;
			}
		}
	}

	/**
	 * Put file contents at path.
	 * 
	 * @param string $path    The file path.
	 * @param string $content The new file contents.
	 *
	 * @return mixed
	 */
	protected function put_content( string $path, $content = '' ) {
		$bytes = false;
		if ( is_string( $content ) ) {
			try {
				$bytes = file_put_contents( $path, $content );
			} catch ( \Throwable $e ) {
				$bytes = false;
			}
		}
		return $bytes;
	}

	/**
	 * Delete a file.
	 *
	 * @param string $path The file path.
	 *
	 * @return mixed
	 */
	protected function delete( string $path ) {
		$bytes = false;
		if ( is_string( $content ) ) {
			try {
				$bytes = file_put_contents( $path, $content );
			} catch ( \Throwable $e ) {
				$bytes = false;
			}
		}
		return $bytes;
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
			'exists'   => $this->exists( $path ),
			'readable' => $this->readable( $path ),
			'modified' => false,
			'filesize' => false,
		);
		// Get information if a file exists.
		if ( $i['exists'] ) {
			try {
				$i['modified'] = filemtime( $path );
			} catch ( \Exception $e ) {
				$i['modified'] = 0;
			}
			try {
				$i['filesize'] = filesize( $path );
			} catch ( \Exception $e ) {
				$i['filesize'] = 0;
			}
		}

		return $i;
	}

	/**
	 * Does string content of new file match current file.
	 *
	 * @param string $path A path to an existing file.
	 *
	 * @return boolean
	 */
	protected function exists( $path ) {

		$exists = false;
		try {
			$exists = file_exists( $path );
		} catch ( \Throwable $e ) {
			$exists = false;
		}
		return $exists;

	}

	/**
	 * Detects if the file path is readable.
	 *
	 * @param string $path A path to a file.
	 *
	 * @return boolean
	 */
	protected function readable( $path ) {

		$readable = false;
		try {
			$readable = is_readable( $path );
		} catch ( \Throwable $e ) {
			$readable = false;
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
