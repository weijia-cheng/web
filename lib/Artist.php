<?
use Safe\DateTimeImmutable;

/**
 * @property ?int $DeathYear
 * @property ?string $UrlName
 * @property ?string $Url
 * @property ?array<string> $AlternateNames
 */
class Artist{
	use Traits\Accessor;
	use Traits\PropertyFromHttp;

	public int $ArtistId;
	public string $Name = '';
	public DateTimeImmutable $Created;
	public DateTimeImmutable $Updated;
	public ?int $DeathYear = null;

	protected string $_UrlName;
	protected string $_Url;
	/** @var array<string> $_AlternateNames */
	protected array $_AlternateNames;


	// *******
	// GETTERS
	// *******

	protected function GetUrlName(): string{
		if(!isset($this->_UrlName)){
			if(!isset($this->Name) || $this->Name == ''){
				$this->_UrlName = '';
			}
			else{
				$this->_UrlName = Formatter::MakeUrlSafe($this->Name);
			}
		}

		return $this->_UrlName;
	}

	protected function GetUrl(): string{
		return $this->_Url ??= '/artworks/' . $this->UrlName;
	}

	/**
	 * @return array<string>
	 */
	protected function GetAlternateNames(): array{
		if(!isset($this->_AlternateNames)){
			$this->_AlternateNames = [];

			$result = Db::Query('
					SELECT *
					from ArtistAlternateNames
					where ArtistId = ?
				', [$this->ArtistId]);

			foreach($result as $row){
				$this->_AlternateNames[] = $row->Name;
			}
		}

		return $this->_AlternateNames;
	}


	// *******
	// METHODS
	// *******

	/**
	 * @throws Exceptions\InvalidArtistException
	 */
	public function Validate(): void{
		$thisYear = intval(NOW->format('Y'));

		$error = new Exceptions\InvalidArtistException();

		$this->Name = trim($this->Name ?? '');

		if($this->Name == ''){
			$error->Add(new Exceptions\ArtistNameRequiredException());
		}
		elseif(strlen($this->Name) > ARTWORK_MAX_STRING_LENGTH){
			$error->Add(new Exceptions\StringTooLongException('Artist Name'));
		}

		if($this->Name == 'Anonymous' && $this->DeathYear !== null){
			$this->DeathYear = null;
		}

		if($this->DeathYear !== null && ($this->DeathYear <= 0 || $this->DeathYear > $thisYear + 50)){
			$error->Add(new Exceptions\InvalidDeathYearException());
		}

		if($error->HasExceptions){
			throw $error;
		}
	}

	public function FillFromHttpPost(): void{
		$this->PropertyFromHttp('Name');
		$this->PropertyFromHttp('DeathYear');
	}

	// ***********
	// ORM METHODS
	// ***********

	/**
	 * @throws Exceptions\ArtistNotFoundException
	 */
	public static function Get(?int $artistId): Artist{
		if($artistId === null){
			throw new Exceptions\ArtistNotFoundException();
		}

		return Db::Query('
				SELECT *
				from Artists
				where ArtistId = ?
			', [$artistId], Artist::class)[0] ?? throw new Exceptions\ArtistNotFoundException();
	}

	/**
	 * @return array<Artist>
	 */
	public static function GetAll(): array{
		return Db::Query('
			SELECT *
			from Artists
			order by Name asc', [], Artist::class);
	}

	/**
	 * @throws Exceptions\ArtistNotFoundException
	 */
	public static function GetByAlternateUrlName(?string $urlName): Artist{
		if($urlName === null){
			throw new Exceptions\ArtistNotFoundException();
		}

		return Db::Query('
				SELECT a.*
					from Artists a
					left outer join ArtistAlternateNames aan using (ArtistId)
					where aan.UrlName = ?
					limit 1
			', [$urlName], Artist::class)[0] ?? throw new Exceptions\ArtistNotFoundException();
	}

	/**
	 * @throws Exceptions\InvalidArtistException
	 */
	public function Create(): void{
		$this->Validate();
		Db::Query('
			INSERT into Artists (Name, UrlName, DeathYear)
			values (?,
			        ?,
			        ?)
		', [$this->Name, $this->UrlName, $this->DeathYear]);

		$this->ArtistId = Db::GetLastInsertedId();
	}

	/**
	 * @throws Exceptions\InvalidArtistException
	 */
	public static function GetOrCreate(Artist $artist): Artist{
		$result = Db::Query('
					SELECT a.*
					from Artists a
					left outer join ArtistAlternateNames aan using (ArtistId)
					where a.UrlName = ?
					    or aan.UrlName = ?
					limit 1
		', [$artist->UrlName, $artist->UrlName], Artist::class);

		if(isset($result[0])){
			return $result[0];
		}
		else{
			$artist->Create();
			return $artist;
		}
	}

	public function Delete(): void{
		Db::Query('
			DELETE
			from Artists
			where ArtistId = ?
		', [$this->ArtistId]);

		Db::Query('
			DELETE
			from ArtistAlternateNames
			where ArtistId = ?
		', [$this->ArtistId]);
	}
}
