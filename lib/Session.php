<?
use Ramsey\Uuid\Uuid;
use Safe\DateTimeImmutable;

/**
 * @property string $Url
 */
class Session{
	use Traits\Accessor;

	public static ?User $User = null;

	public int $UserId;
	public DateTimeImmutable $Created;
	public string $SessionId;

	public string $_Url;


	// *******
	// GETTERS
	// *******

	protected function GetUrl(): string{
		if(!isset($this->_Url)){
			$this->_Url = '/sessions/' . $this->SessionId;
		}

		return $this->_Url;
	}


	// *******
	// METHODS
	// *******

	/**
	 * @param ?string $identifier Either the email, or the UUID, of the user attempting to log in.
	 *
	 * @throws Exceptions\InvalidLoginException
	 * @throws Exceptions\PasswordRequiredException
	 */
	public function Create(?string $identifier = null, ?string $password = null): void{
		try{
			Session::$User = User::GetIfRegistered($identifier, $password);
			$this->UserId = Session::$User->UserId;

			$existingSessions = Db::Query('
							SELECT SessionId,
							       Created
							from Sessions
							where UserId = ?
						', [$this->UserId]);

			if(sizeof($existingSessions) > 0){
				$this->SessionId = $existingSessions[0]->SessionId;
				$this->Created = $existingSessions[0]->Created;
			}
			else{
				$uuid = Uuid::uuid4();
				$this->SessionId = $uuid->toString();

				$this->Created = NOW;
				Db::Query('
						INSERT into Sessions (UserId, SessionId, Created)
						values (?,
						        ?,
						        ?)
					', [$this->UserId, $this->SessionId, $this->Created]);
			}

			self::SetSessionCookie($this->SessionId);
		}
		catch(Exceptions\UserNotFoundException){
			throw new Exceptions\InvalidLoginException();
		}
	}

	public static function SetSessionCookie(string $sessionId): void{
		/** @throws void */
		setcookie('sessionid', $sessionId, ['expires' => intval((new DateTimeImmutable('+1 week'))->format(Enums\DateTimeFormat::UnixTimestamp->value)), 'path' => '/', 'domain' => SITE_DOMAIN, 'secure' => true, 'httponly' => false, 'samesite' => 'Lax']); // Expires in two weeks
	}


	// ***********
	// ORM METHODS
	// ***********

	/**
	 * @throws Exceptions\SessionNotFoundException
	 */
	public static function Get(?string $sessionId): Session{
		if($sessionId === null){
			throw new Exceptions\SessionNotFoundException();
		}

		$result = Db::Query('
					SELECT *
					from Sessions
					where SessionId = ?
				', [$sessionId], Session::class);

		return $result[0] ?? throw new Exceptions\SessionNotFoundException();
	}

	public static function InitializeFromCookie(): void{
		$sessionId = HttpInput::Str(COOKIE, 'sessionid');

		if($sessionId !== null){
			$result = Db::Query('
						SELECT u.*
						from Users u
						inner join Sessions s using (UserId)
						where s.SessionId = ?
					', [$sessionId], User::class);

			if(sizeof($result) > 0){
				self::SetSessionCookie($sessionId);
				Session::$User = $result[0];
			}
		}
	}
}
