<?php 
namespace Mouf\Installer;

use Composer\Util\RemoteFilesystem;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Installer;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Factory;
use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;

/**
 * This class is in charge of handling the installation of an external package
 * that will be downloaded.
 * 
 * 
 * @author David Négrier
 */
class ArchiveInstaller extends LibraryInstaller {
	
	protected $rfs;
	
	/**
	 * Initializes library installer.
	 *
	 * @param IOInterface $io
	 * @param Composer    $composer
	 * @param string      $type
	 */
	public function __construct(IOInterface $io, Composer $composer, $type = 'library')
	{
		parent::__construct($io, $composer, $type);
		$this->rfs = new RemoteFilesystem($io);
	}
	
	
	/**
	 * {@inheritDoc}
	 */
	public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
	{
		parent::update($repo, $initial, $target);
		
		self::downloadAndExtractFile($package);
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
	{
		parent::install($repo, $package);
		
		self::downloadAndExtractFile($package);
	}
	
	/**
	 * Downloads and extracts the package, only if the URL to download has not been downloaded before.
	 * 
	 * @param PackageInterface $package
	 * @throws \RuntimeException
	 * @throws \UnexpectedValueException
	 */
	private static function downloadAndExtractFile(PackageInterface $package) {
		$extra = $package->getExtra();
		if (isset($extra['url'])) {
			$url = $extra['url'];
				
			if (isset($extra['target-dir'])) {
				$targetDir = $extra['target-dir'];
			} else {
				$targetDir = 'vendor/'.$package->getName();
			}
			$targetDir = trim($targetDir, '/');
				
			// First, try to detect if the archive has been downloaded
			// If yes, do nothing.
			// If no, let's download the package.
			if (self::getLastDownloadedFileUrl($package) == $url) {
				return;
			}
				
			// Download (using code from FileDownloader)
			$fileName = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_BASENAME);
				
			if (!extension_loaded('openssl') && 0 === strpos($url, 'https:')) {
				throw new \RuntimeException('You must enable the openssl extension to download files via https');
			}
				
				
			$this->io->write("    - Downloading <info>" . $fileName . "</info> from <info>".$url."</info>");
				
			$this->rfs->copy(parse_url($url, PHP_URL_HOST), $url, $fileName);
				
			if (!file_exists($fileName)) {
				throw new \UnexpectedValueException($url.' could not be saved to '.$fileName.', make sure the'
						.' directory is writable and you have internet connectivity');
			}
				
			// Extract using ZIP downloader
				
			// TODO
				
			self::setLastDownloadedFileUrl($package, $url);
		}
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
	{
		parent::uninstall($repo, $package);
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function supports($packageType)
	{
		return 'archive-package' === $packageType;
	}
	
	/**
	 * Returns the URL of the last file that this install process ever downloaded.
	 * 
	 * @param PackageInterface $package
	 * @return string
	 */
	public static function getLastDownloadedFileUrl(PackageInterface $package) {
		$packageDir = self::getPackageDir($package);
		if (file_exists($packageDir."download-status.txt")) {
			return file_get_contents($packageDir."download-status.txt");
		} else {
			return null;
		}
	}

	/**
	 * Saves the URL of the last file that this install process downloaded into a file for later retrieval.
	 * 
	 * @param PackageInterface $package
	 * @param unknown $url
	 */
	public static function setLastDownloadedFileUrl(PackageInterface $package, $url) {
		$packageDir = self::getPackageDir($package);
		file_put_contents($packageDir."download-status.txt", $url);
	}
	
	/**
	 * Returns the package directory, with a trailing /
	 * 
	 * @param PackageInterface $package
	 * @return string
	 */
	public static function getPackageDir(PackageInterface $package) {
		return __DIR__."/../../../../../".$package->getName()."/";
	}
}