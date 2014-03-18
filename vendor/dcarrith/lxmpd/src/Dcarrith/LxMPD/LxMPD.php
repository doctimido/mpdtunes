<?php namespace Dcarrith\LxMPD;
/**
* LxMPD.php: A Laravel-ready class for controlling MPD
*/

use Config;
use Dcarrith\LxMPD\Exception\MPDException as MPDException; 
use Dcarrith\LxMPD\Connection\MPDConnection as MPDConnection;
 
/**
* A Laravel-ready class for controlling MPD
* @package MPD
*/
class LxMPD {

        // MPD Responses
        const MPD_OK = 'OK';
        const MPD_ERROR = 'ACK';

        // Connection, read, write errors
        const MPD_WRITE_FAILED = -3;
        const MPD_STATUS_EMPTY = -4;
        const MPD_UNEXPECTED_OUTPUT = -5;
        const MPD_TIMEOUT = -6;
	const MPD_CONNECTION_NOT_ESTABLISHED = -7;
 
	// MPD ACK_ERROR constants from Ack.hxx
	const ACK_ERROR_NOT_LIST = 1;
	const ACK_ERROR_ARG = 2;
	const ACK_ERROR_PASSWORD = 3;
	const ACK_ERROR_PERMISSION = 4;
	const ACK_ERROR_UNKNOWN = 5;
	const ACK_ERROR_NO_EXIST = 50;
	const ACK_ERROR_PLAYLIST_MAX = 51;
	const ACK_ERROR_SYSTEM = 52;
	const ACK_ERROR_PLAYLIST_LOAD = 53;
	const ACK_ERROR_UPDATE_ALREADY = 54;
	const ACK_ERROR_PLAYER_SYNC = 55;
	const ACK_ERROR_EXIST = 56;

	// Missing tag errors
	const ESSENTIAL_TAGS_MISSING = 70;
	const ESSENTIAL_ID3_TAGS_MISSING = 71;
	const ESSENTIAL_MPD_TAGS_MISSING = 72;

	// A general command failed 
	const MPD_COMMAND_FAILED = -100;

	// Authentication errors
	const MPD_NO_PASSWORD = -101;
	const MPD_BAD_PASSWORD = -102;
	const MPD_NO_READ_ACCESS = -103;

	// Output array chunk sizes
	const PLAYLISTINFO_CHUNK_SIZE = 8;

	// Variable to switch on and off debugging
	private $_debugging = false;

	// Variable to specify whether or not playlist tracks should be filtered down to only contain essential tags
	private $_tagFiltering = true;

	// Variable to specify whether or not to throw missing tag exceptions for tracks that are missing essetial tags
	private $_throwMissingTagExceptions = false;

	// Variable for storing properties available via PHP magic methods: __set(), __get(), __isset(), __unset()
	private $_properties = array();

	// The essential id3 tags that we need when chunking an array of output into chunks of 8 elements
	private $_essentialID3Tags = array( "Artist", "Album", "Title", "Track", "Time" );

	// The essential MPD tags that we need in combination with essentialID3Tags when chunking an array of output into chunks of 8 elements
	private $_essentialMPDTags = array( "file", "Pos", "Id" );

	// This is an array of commands that return either single tracks or a list of tracks that would contain tags that we could filter
	private $_outputContainsTracks = array( 'playlistinfo' );

        // This is an array of commands whose output is expected to be an array
        private $_expectArrayOutput = array( 'commands', 'decoders', 'find', 'list', 'listall', 'listallinfo', 'listplaylist', 'listplaylistinfo', 'listplaylists', 'notcommands', 'lsinfo', 'outputs', 'playlist', 'playlistfind', 'playlistinfo', 'playlistsearch', 'plchanges', 'plchangesposid', 'search', 'tagtypes', 'urlhandlers' );
 
	// This is an array of MPD commands that are available through the __call() magic method
	private $_methods = array( 'add', 'addid', 'clear', 'clearerror', 'close', 'commands', 'consume', 'count', 'crossfade', 'currentsong', 'decoders', 'delete', 'deleteid', 'disableoutput', 'enableoutput', 'find', 'findadd', 'idle', 'kill', 'list', 'listall', 'listallinfo', 'listplaylist', 'listplaylistinfo', 'listplaylists', 'load', 'lsinfo', 'mixrampdb', 'mixrampdelay', 'move', 'moveid', 'next', 'notcommands', 'outputs', 'password', 'pause', 'ping', 'play', 'playid', 'playlist', 'playlistadd', 'playlistclear', 'playlistdelete', 'playlistfind', 'playlistid', 'playlistinfo', 'playlistmove', 'playlistsearch', 'plchanges', 'plchangesposid', 'previous', 'random', 'rename', 'repeat', 'replay_gain_mode', 'replay_gain_status', 'rescan', 'rm', 'save', 'search', 'seek', 'seekid', 'setvol', 'shuffle', 'single', 'stats', 'status', 'sticker', 'stop', 'swap', 'swapid', 'tagtypes', 'update', 'urlhandlers' );

	// This is an array of MPD commands that should return a bool
	private $_responseShouldBeBoolean = array( 'delete', 'password' );

        /**
         * Set connection paramaters.
         * @param $connection type MPDConnection class
         * @return void
         */
        function __construct( MPDConnection $connection ) {

		// Set the connection to the injected connection object
		$this->connection = $connection;
        }

        /**
         * Authenticate to the MPD server 
         * @return bool
         */
        public function authenticate() {

                // Check whether the socket is already connected
                if( !$this->connection->established ) {

			// Throw an MPDException along with the connection errors
			throw new MPDException( 'The connection to MPD has not been established', self::MPD_CONNECTION_NOT_ESTABLISHED );
                }
                                
		// Send the connection password
                if( $this->connection->hasPassword() ) {

			// Authenticate to MPD 
			if( !$this->password( $this->connection->password )) {

				// We might as well not be connected
				$this->connection->close();

				// If the password fails, then we're not going to be able to do much
				throw new MPDException( 'MPD did not like the provided password', self::MPD_BAD_PASSWORD );
			}

		} else {
					
			// We might as well not be connected
			$this->connection->close();

			// If we don't have a password, then we're not going to be able to do much
			throw new MPDException( 'Must supply a password to authenticate to MPD', self::MPD_NO_PASSWORD );
		}

		// Password must have been accepted
		return true;
	}

        /**
         * Writes data to the MPD socket
         * @param string $data The data to be written
         * @return bool
         */
        private function write( $data ) {
 
		if( !$this->connection->established ) {
                        $this->connection->establish();
                }

		if( !fputs( $this->connection->socket, "$data\n" ) ) {
			throw new MPDException( 'Failed to write to MPD socket', self::MPD_WRITE_FAILED );
                }

                return true;
        }

        /**
         * Reads data from the MPD socket
         * @return array Array of lines of data
         */
        private function read() {

                // Check for a connection
                if( !$this->connection->established ) {
                        $this->connection->establish();
                }

                // Set up the array to use for storing the read in MPD response
                $response = array();

		// This will be used in case there is an empty array as the response
		$ok = false;

		// Get the stream meta-data
                $info = stream_get_meta_data( $this->connection->socket );

                // Wait for output to finish or time out
                while( !feof( $this->connection->socket ) && !$info['timed_out'] ) {

                        $line = trim( fgets( $this->connection->socket ));

			$info = stream_get_meta_data( $this->connection->socket );

                        // We get empty lines sometimes. Ignore them.
                        if( empty( $line ) ) {

                                continue;

                        } else if( strcmp( self::MPD_OK, $line ) == 0 ) {

				$ok = true;
                                break;

                        } else if( strncmp( self::MPD_ERROR, $line, strlen( self::MPD_ERROR ) ) == 0 && preg_match( '/^ACK \[(.*?)\@(.*?)\] \{(.*?)\} (.*?)$/', $line, $matches ) ) {

				// First item in matches will be the errorConstant
				// Second item in matches will be the index of the failed command
				// Third item in matches will be the original command that was run
				// Fourth item in matches will be the error message
				list( $constant, $index, $command, $error ) = $matches;

				throw new MPDException( 'Command failed: '.$errorMessage, self::MPD_COMMAND_FAILED );
                        
			} else {
                        
			        $response[] = $line;
                        }
                }

                if( $info['timed_out'] ) {

			$this->connection->close();

                        throw new MPDException( 'Command timed out', self::MPD_TIMEOUT );

                } else {

			if( !count($response) ) {
			
				$response = $ok;
			}

                        return $response;
                }
        }

        /**
         * Runs a given command with arguments
         * @param string $command The command to execute
         * @param string|array $args The command's argument(s)
         * @param int $timeout The script's timeout, in seconds
         * @return array Array of parsed output
         */
        public function runCommand( $command, $args = array(), $timeout = null ) {

		// Set a timeout so it's always set to either the default or the passed in parameter
		$timeout = ( isset( $timeout ) ? intval( $timeout ) : $this->connection->timeout );

                // Trim and then cast the command to a string, just to make sure
                $toWrite = strval( trim( $command ));

		// If the args is an array, then first escape double quotes in every element, then implode to strings delimted by enclosing quotes
		if( is_array( $args ) && ( count( $args ) > 0 )) {
	 	
			$toWrite .= ' "' . implode('" "', str_replace( '"', '\"', $args )) . '"';
		}

                // Write command to MPD socket
                $this->write( $toWrite );

		// Set the timeout in seconds
		$this->connection->setStreamTimeout( $timeout );		

                // Read the response from the MPD socket
                $response = $this->read();

		// Set the timeout in seconds
		$this->connection->setStreamTimeout( $timeout );		

                // Return the parsed response array
                return $this->parse( $response, $command, $args );
        }

        /**
         * Parses an array of output lines from MPD into a common array format
         * @param array $response the output read from the connection to MPD
         * @return mixed (string || array)
         */
        private function parse( $response, $command = '', $args = array() ) {

		// This is the array for storing all the parsed output
                $parsed = array();

		// If the response is a boolean, and the command is one that expects a boolean response, then return the response
		if( is_bool($response) ) {

			if( in_array( $command, $this->_responseShouldBeBoolean )) {

				return $response;
			
			} else {

				// If the command isn't expecting a boolean result, then we need to set the response back to an empty array
				$response = array();
			}
		}

		// If the response from MPD was an empty array, then just return the empty parsed array
		if( !count( $response ) ) {
			return $parsed;
		}
	
		switch( $command ) {

			// This will parse out a list of something like artists or albums into a simple array of values
			case 'list' :
			case 'listplaylist' :
			case 'listplaylists' :

				foreach( $response as $line ) {

					// Get the key value pairs from the line of output
					preg_match('/(.*?):\s(.*)/', $line, $matches);

					if( count($matches) != 3 ) {
			
						continue;
					}

					// Put the cleaned up matched pieces into the variables we'll be using
					list( $subject, $key, $value ) = $matches;

					// listplaylists requires special treatment
					if( $command == "listplaylists") {

						// We only care about the elements with the key 'playlist'
						if( $key == "playlist" ) {

							// We only need an array of playlist names
							$parsed[] = $value;
						}

					} else {

						// For playlists that aren't the current playlist, we only need an array of values
						$parsed[] = $value;
					}
				}

				return $parsed;

				break;

			// listplaylistinfo
			// playlistinfo
			// statistics
			// stats
			// idle	
			default :

				$items = array();

				foreach( $response as $line ) {

					// Get the key value pairs from the line of output
					preg_match('/(.*?):\s(.*)/', $line, $matches);

					// Put the cleaned up matched pieces into the variables we'll be using
					list( $subject, $key, $value ) = $matches;
	
					// The response output from certain commands like statistics and stats will never 
					// meet this condition, so therefore the items array will always be built as an
					// associative array with key => value pairs.  The response output from commands
					// like list, or list 
					if( array_key_exists( $key, $items ) ) {

						// Append the track array onto the array of parsedOutput to be returned
						$parsed[] = $items;
						
						// Initialize a new track to compile
						$items = array( $key => $value );
					
					} else {

						// Set the key value pair in the track array
						$items[ $key ] = $value;
					}
				}
			
				if( in_array( $command, $this->_expectArrayOutput ) ) {

					// Append the last items array onto the array of parsedOutput to return
					$parsed[] = $items;
				
				} else {

					$parsed = $items;
				} 
				
				// If the output contains one or more tracks, then we can filter and report on missing tags if needed
				if( in_array( $command, $this->_outputContainsTracks )) {

					if( $this->_tagFiltering ) {

						$parsed = $this->filterOutUnwantedTags( $parsed );
					}

					if( $this->_throwMissingTagExceptions ) {

						$this->reportOnMissingTags( $command, $parsed );
					}
				}

				return $parsed;

				break;
		}
	
		return false;
        }

	/* refreshInfo updates all class properties with the values from the MPD server.
     	 *
	 * NOTE: This function is automatically called upon Connect()
	 */
	public function refreshInfo() {
        	
		// Get the Server Statistics
		$this->statistics = $this->stats();
	
		// Extract the statistics variables and store them as properties of the class instance
		$this->uptime		= ( isset($this->statistics['uptime']) 		? $this->statistics['uptime'] : 	0  );
		$this->playtime		= ( isset($this->statistics['playtime']) 	? $this->statistics['playtime'] : 	0  );
		$this->artists		= ( isset($this->statistics['artists']) 	? $this->statistics['artists'] : 	0  );
		$this->albums		= ( isset($this->statistics['albums']) 		? $this->statistics['albums'] : 	0  );
		$this->songs		= ( isset($this->statistics['songs']) 		? $this->statistics['songs'] : 		0  );
		$this->db_playtime	= ( isset($this->statistics['db_playtime']) 	? $this->statistics['db_playtime'] : 	0  );	
		$this->db_update	= ( isset($this->statistics['db_update']) 	? $this->statistics['db_update'] : 	0  );

		// Get the Server Status
		$this->status = $this->status();
        	
		// Get the Playlist
		$this->playlist = $this->playlistinfo();

		// Get a count of how many tracks are in the playlist    		
		$this->playlist_count = count( $this->playlist );

        	// Let's store the state for easy access as a property
		$this->state = ( isset($this->status['state'] ) ? $this->status['state'] : 'stop' );
		
		// If the state is play or pause then we want to create some custom properties for easier access to certain stats
		if ( ($this->state == "play") || ($this->state == "pause") ) {

			$this->current_track_id = $this->status['song'];
			list ($this->current_track_position, $this->current_track_length ) = explode(":", $this->status['time']);

		} else {

			$this->current_track_id = 0;
			$this->current_track_position = 0;
			$this->current_track_length = 0;
		}

		// Extract out all of the status variables and store them as properties of the class instance
		$this->repeat		= ( isset($this->status['repeat']) 		? $this->status['repeat'] : 		0  );
		$this->random		= ( isset($this->status['random']) 		? $this->status['random'] : 		0  );
		$this->single		= ( isset($this->status['single']) 		? $this->status['single'] : 		0  );
		$this->consume		= ( isset($this->status['consume']) 		? $this->status['consume'] : 		0  );
		$this->volume		= ( isset($this->status['volume']) 		? $this->status['volume'] : 		0  );
		$this->xfade 		= ( isset($this->status['xfade']) 		? $this->status['xfade'] : 		0  );
		$this->mixrampdb 	= ( isset($this->status['mixrampdb']) 		? $this->status['mixrampdb'] : 		0  );
		$this->mixrampdelay 	= ( isset($this->status['mixrampdelay']) 	? $this->status['mixrampdelay'] : 	0  );
		$this->playlist_id 	= ( isset($this->status['playlist']) 		? $this->status['playlist'] : 		0  );
		$this->playlist_length 	= ( isset($this->status['playlist_length']) 	? $this->status['playlist_length'] : 	0  );
		$this->song 		= ( isset($this->status['song']) 		? $this->status['song'] : 		0  );
		$this->songid 		= ( isset($this->status['songid']) 		? $this->status['songid'] : 		0  );
		$this->nextsong 	= ( isset($this->status['nextsong']) 		? $this->status['nextsong'] : 		0  );
		$this->nextsongid 	= ( isset($this->status['nextsongid']) 		? $this->status['nextsongid'] : 	0  );
		$this->time 		= ( isset($this->status['time']) 		? $this->status['time'] : 		0  );
		$this->elapsed		= ( isset($this->status['elapsed'])		? $this->status['elapsed'] : 		0  );
		$this->bitrate 		= ( isset($this->status['bitrate']) 		? $this->status['bitrate'] : 		0  );
		$this->audio	 	= ( isset($this->status['audio']) 		? $this->status['audio'] : 		'' );
	}
 
	/**
         * Excecuting the 'idle' function requires turning off timeouts, since it could take a long time
         * @param array $subsystems An array of particular subsystems to watch
         * @return string|array
         */
        public function idle( $subsystems = array() ) {
                
		return $this->runCommand( 'idle', $subsystems, 1800 );
        }

	/**
	 * GetFirstTrack gets the first track of an album
	 * @param scope_key is to give scope to the find command
	 * @param scope_value is the value of the scope
	 * @return firstTrack 
	 */
	public function getFirstTrack( $scope_key = "album", $scope_value = null ) {

		return current( $this->find( "album", $scope_value ))['file'];
	}

	/**
	 * getEssentialTags combines the essential ID3 as well as MPD-specific tags 
	 * @return array  
	 */
	public function getEssentialTags() {

		// Merge together the two types of tags as one array of essentialTags
		return array_merge( $this->_essentialMPDTags, $this->_essentialID3Tags );
	}

	/**
	 * reportOnMissingTags will find any tracks that are missing essentials tags and throws an exception 
	 * 	that contains enough information to track down the missing tags so the user can fill them in
	 *	with the id3 editor of their choice
	 * @param string $command is the command that was run which we want to pass through to the exception message
	 * @param array $tracks is the array of tracks to loop through
	 * @throws MPDException
	 * @return void
	 */
	public function reportOnMissingTags( $command, $tracks ) {

		// getEssentialTags combines the essential ID3 as well as MPD-specific tags 
		$essentialTags = $this->getEssentialTags();
	
		// Loop through the tracks array so we can replace each track with a simple array of missing tags
		$incompleteTracks = array_filter( array_map( function( $track ) use ( $essentialTags ) {

			// Flip the essential tags array so the values are keys.
			// Take the diff_key of that and $track so we're left with only tags that are in the essentialTags array, but not in $track.
			// Flip the result of that back around so the keys are values again.
			$missingTags = array_flip( array_diff_key( array_flip( $essentialTags ), $track ));

			// If there are missing tags, then return the array element using the MPD track Id as the key so we can retrieve more info later.
			return (count($missingTags) ? (array($track['Id'] => $missingTags)) : array());

		}, $tracks), function( $missing ) {

			// Filter out any empty arrays so we're only left with the arrays of the incomplete tracks
			return (count($missing));
		});

		// If we have any tracks that are missing essential tags, then throw an exception to alert the user
		if( count($incompleteTracks) ) {
					
			$detailedMessage = "";

			// Loop through the incomplete tracks so we can retrieve more info about each track and build the exception message	
			foreach( $incompleteTracks as $incompleteTrack ) {
	
				// Get the id from the incompleteTrack array	
				$id = key($incompleteTrack);

				// Retrieve more information about the track that's missing tags
				$track = $this->playlistid( $id );

				// Get the name of the artist
				$artist = $track['Artist'];

				// Get the name of the album
				$album = $track['Album'];

				// Complile a detailed message about the track
				$detailedMessage .= "Track #".$id." from the artist '".$artist.",' specifically, the album '".$album."', is missing tag".((count($incompleteTrack) > 1) ? "s: " : ": ").implode( ", ", current($incompleteTrack) ).".  ";
			}

			// There must be some essential tags missing from one or more tracks in the playlist
			throw new MPDException( 'The command "'.$command.'" has retrieved some tracks that are missing essential tag elements.  Please clean up any deficient id3 tags and try again.  The essentials tags are as follows: '.implode(", ", $essentialID3Tags).'.  Details: '.$detailedMessage, self::ESSENTIAL_TAGS_MISSING );
		}
	}

	public function filterOutUnwantedTags( $tracks ) {

		// getEssentialTags combines the essential ID3 as well as MPD-specific tags 
		$essentialTags = $this->getEssentialTags();

		// Loop through the tracks array so we can modify each track and filter out all but the essential tags
		return array_map( function( $track ) use ( $essentialTags ) {

			// Flip the essential tags array so the values are keys
			// Then intersect that with the track array so we're left with the essential tags
			return array_intersect_key( $track, array_flip( $essentialTags ) );

		}, $tracks);
	}

	public function playlistExists( $name ) {

		return in_array( $name, $this->listplaylists() );
	}

	/**
         * Checks whether the instance of LxMPD is connected to MPD
         * @return bool
         */
        public function isConnected() {

                return $this->connection->established;
        }

	/**
	 * PHP magic methods __call(), __get(), __set(), __isset(), __unset()
	 *
	 */
 
        public function __call( $name, $arguments ) {
	
                if( in_array( $name, $this->_methods ) ) {
                        return $this->runCommand( $name, $arguments );
                }
        }

	public function __get($name) {

		if ( array_key_exists( $name, $this->_properties )) {
			return $this->_properties[$name];
		}

		$trace = debug_backtrace();

		trigger_error(	'Undefined property via __get(): ' . $name .
				' in ' . $trace[0]['file'] .
				' on line ' . $trace[0]['line'],
				E_USER_NOTICE	);
		
		return null;
	}

	public function __set( $name, $value ) {
		$this->_properties[$name] = $value;
	}

	public function __isset( $name ) {
		return isset( $this->_properties[$name] );
	}

	public function __unset( $name ) {
		unset( $this->_properties[$name] );
	}
}
