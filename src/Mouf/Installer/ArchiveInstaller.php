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
		
		$this->downloadAndExtractFile($package);
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
	{
		parent::install($repo, $package);
		
		$this->downloadAndExtractFile($package);
	}
	
	/**
	 * Downloads and extracts the package, only if the URL to download has not been downloaded before.
	 * 
	 * @param PackageInterface $package
	 * @throws \RuntimeException
	 * @throws \UnexpectedValueException
	 */
	private function downloadAndExtractFile(PackageInterface $package) {
		$extra = $package->getExtra();
		if (isset($extra['url'])) {
			$url = $extra['url'];
				
			if (isset($extra['target-dir'])) {
				$targetDir = $extra['target-dir'];
			} else {
				$targetDir = '.';
			}
			$targetDir = './'.trim($targetDir, '/');
			
			// First, try to detect if the archive has been downloaded
			// If yes, do nothing.
			// If no, let's download the package.
			if (self::getLastDownloadedFileUrl($package) == $url) {
				return;
			}
				
			// Download (using code from FileDownloader)
			$fileName = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_BASENAME);
			$extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
				
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
			if ($extension == 'zip') {
				$this->extractZip($fileName, $targetDir);
			} elseif ($extension == 'tar' || $extension == 'gz' || $extension == 'bz2') {
				$this->extractTgz($fileName, $targetDir);
			}
			
			// Delete archive once download is performed
			unlink($fileName);
				
			// Save last download URL
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
	
	/**
	 * Extract ZIP (copied from Composer's ZipDownloader)
	 * 
	 * @param string $file
	 * @param string $path
	 * @throws \RuntimeException
	 * @throws \UnexpectedValueException
	 */
	protected function extractZip($file, $path)
	{
		if (!class_exists('ZipArchive')) {
			$error = 'You need the zip extension enabled to use the ZipDownloader';
	
			// try to use unzip on *nix
			if (!defined('PHP_WINDOWS_VERSION_BUILD')) {
				$command = 'unzip '.escapeshellarg($file).' -d '.escapeshellarg($path);
				if (0 === $this->process->execute($command, $ignoredOutput)) {
					return;
				}
	
				$error = "Could not decompress the archive, enable the PHP zip extension or install unzip.\n".
						'Failed to execute ' . $command . "\n\n" . $this->process->getErrorOutput();
			}
	
			throw new \RuntimeException($error);
		}
	
		$zipArchive = new ZipArchive();
	
		if (true !== ($retval = $zipArchive->open($file))) {
			throw new \UnexpectedValueException($this->getErrorMessage($retval, $file));
		}
	
		if (true !== $zipArchive->extractTo($path)) {
			throw new \RuntimeException("There was an error extracting the ZIP file. Corrupt file?");
		}
	
		$zipArchive->close();
	}
	
	/**
	 * Extract tar, tar.gz or tar.bz2 (copied from Composer's TarDownloader)
	 * 
	 * @param string $file
	 * @param string $path
	 */
	protected function extractTgz($file, $path)
	{
		// Can throw an UnexpectedValueException
		$archive = new \PharData($file);
		$archive->extractTo($path, null, true);
	}
}