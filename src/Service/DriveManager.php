<?php
// https://developers.google.com/drive/api/v3/quickstart/php
// https://console.developers.google.com/apis/credentials?project=viag2e&authuser=0&organizationId=543696805164

namespace App\Service;

use App\Entity\BankExport;
use App\Entity\File;
use Exception;
use DateTime;
use Google_Client;
use Google_Exception;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use Google_Service_Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

class DriveManager
{
    private $_params;

    private $_credentials;
    private $_token;

    private $_client;
    private $_drive;

    private $_folders;

    private $_connected = false;
    private $_isdev = false;

    public function __construct(ParameterBagInterface $params)
    {
        $this->_params = $params;

        $this->_credentials = $this->_params->get('new_google_credentials');
        $this->_token       = $this->_params->get('new_google_token');
       // $this->_credentials = $this->_params->get('google_credentials');
        //$this->_token       = $this->_params->get('google_token');
        if ($params->get('kernel.environment') != 'prod') {
            $this->_isdev = false;
            return;
        }

     
    }

    public function connect() {
        try {
            $this->_client = $this->getClient();
            $this->_drive = new Google_Service_Drive($this->_client);
			/*
			echo "pre lecture<br>";
            $results = $this->_drive->files->listFiles([
                'q'      => "'root' in parents and mimeType = 'application/vnd.google-apps.folder'",
                'fields' => 'files(id, name)',
            ]);
			echo "creation repertoire<br>";
			$folderMetadata = new Google_Service_Drive_DriveFile([
				'name' => 'Mon Nouveau Dossier 22',
				'mimeType' => 'application/vnd.google-apps.folder',
				'parents'  => ['1maR2RuhHHBNu_xgXT5A0ePStkuLvnx2J'] // Dossier parent
			]);

			$folder = $this->_drive->files->create($folderMetadata, ['fields' => 'id']);
			echo "lecture<br>";
			//var_dump($results->getFiles());
			echo "Dossier créé avec l'ID : " . $folder->id;
			
			echo "<br>";
			//var_dump($results->getFiles());echo "\n";
            
            foreach ($results->getFiles() as $file) {
                echo "Dossier : " . $file->getName() . " (ID: " . $file->getId() . ")<br>";
            }*/
        } catch (Google_Exception $e) {
            exit("p1 ".$e->getMessage());
        } catch (Exception $e) {
            exit("p2 ".$e->getMessage());
        }

        
    }

    public function addExport(string $fileName, string $file)
    {
        if($this->_isdev) {
            return $this->dev_fake_id();
        }

        if(!$this->_connected) {
            $this->connect();
        }

        $driveFile = new Google_Service_Drive_DriveFile();
        $driveFile->setName($fileName);
        $driveFile->setParents([$this->getExportsFolderId()]);

        $f = $this->_drive->files->create($driveFile, [
            'data' => $file,
            'mimeType' => 'text/xml',
            'uploadType' => 'multipart',
            'fields' => 'id'
        ]);

        return $f->getId();
    }

    public function addFile(string $fileName, string $filePath, int $type, int $warrantId)
    {
        if($this->_isdev) {
            return $this->dev_fake_id();
        }

        if(!$this->_connected) {
            $this->connect();
        }
        

        $driveFile = new Google_Service_Drive_DriveFile();
        $driveFile->setName($fileName);
        $driveFile->setParents([$this->getFoldersId($warrantId)[$type]]);
        $f = $this->_drive->files->create($driveFile, [
            'data' => file_get_contents($filePath),
            'mimeType' => mime_content_type($filePath),
            'uploadType' => 'multipart',
            'fields' => 'id'
        ]);
		
        return $f->getId();
    }

    private function createFolder(string $name, array $parents): string
    {
        $driveFile = new Google_Service_Drive_DriveFile();
        $driveFile->setName($name);
        $driveFile->setMimeType('application/vnd.google-apps.folder');
        $driveFile->setParents($parents);
        $file = $this->_drive->files->create($driveFile, ['fields' => 'id']);
        return $file->getId();
    }

    public function getExportsFolderId(): string
    {
        $results = $this->_drive->files->listFiles([
            'q'        => "mimeType='application/vnd.google-apps.folder' and '{$this->_params->get('new_google_folder')}' in parents and name = 'Exports'",
            'pageSize' => 1,
            'fields'   => 'files(id)'
        ]);

        $folder = null;
        if (count($results->getFiles()) > 0) {
            $folder = $results->getFiles()[0]->getId();
        } else {
            $folder = $this->createFolder('Exports', [$this->_params->get('new_google_folder')]);
        }

        return $folder;
    }

    public function getFoldersId($warrantId): array
    {
        if (!empty($this->_folders[$warrantId])) {
            return $this->_folders[$warrantId];
        }

        $results = $this->_drive->files->listFiles([
            'q'        => "mimeType='application/vnd.google-apps.folder' and '{$this->_params->get('new_google_folder')}' in parents and name = 'Mandat #$warrantId'",
            'pageSize' => 1,
            'fields'   => 'files(id)'
        ]);

        $folders = [];
        if (count($results->getFiles()) > 0) {
            $folders['warrant'] = $results->getFiles()[0]->getId();
        } else {
            $folders['warrant'] = $this->createFolder('Mandat #'.$warrantId, [$this->_params->get('new_google_folder')]);
        }

        $results = $this->_drive->files->listFiles([
            'q'        => "mimeType='application/vnd.google-apps.folder' and '{$folders['warrant']}' in parents",
            'pageSize' => 10,
            'fields'   => 'files(id, name)'
        ]);

        foreach ($results->getFiles() as $folder) {
            if ($folder->getName() == 'Documents') {
                $folders[File::TYPE_DOCUMENT] = $folder->getId();
            } elseif ($folder->getName() == 'Factures') {
                $folders[File::TYPE_INVOICE] = $folder->getId();
            } elseif ($folder->getName() == 'Recapitulatifs') {
                $folders[File::TYPE_RECAP] = $folder->getId();
            }
        }

        if (!isset($folders[File::TYPE_DOCUMENT])) {
            $folders[File::TYPE_DOCUMENT] = $this->createFolder('Documents', [$folders['warrant']]);
        }

        if (!isset($folders[File::TYPE_INVOICE])) {
            $folders[File::TYPE_INVOICE] = $this->createFolder('Factures', [$folders['warrant']]);
        }

        if (!isset($folders[File::TYPE_RECAP])) {
            $folders[File::TYPE_RECAP] = $this->createFolder('Recapitulatifs', [$folders['warrant']]);
        }

        $this->_folders[$warrantId] = $folders;

        return $folders;
    }

    public function getExport(BankExport $export): ?string
    {
        if($this->_isdev) {
            return null;
        }

        if(!$this->_connected) {
            $this->connect();
        }

        try {
            $driveFile = $this->_drive->files->get($export->getDriveId(), ['alt' => 'media']);

            $path = $this->_params->get('tmp_files_dir').'/'.$export->getName();

            $fileSystem = new Filesystem();
            $fileSystem->remove([$path]);
            $fileSystem->dumpFile($path, $driveFile->getBody()->getContents());

            return $path;
        } catch (Google_Service_Exception $ex) {
            return null;
        }
    }

    public function getFile(File $file): ?string
    {
        if($this->_isdev) {
            return null;
        }


        //en fonction de la date "2025-02-12 19:19:49"
        // Date de référence
        $referenceDate = new DateTime("2025-02-12 19:19:49");

        // Récupération de la date du fichier
        $fileDate = ($file->getDate());

        if ($fileDate > $referenceDate) {
            $this->_credentials = $this->_params->get('new_google_credentials');
            $this->_token       = $this->_params->get('new_google_token');
            $this->connect();
            
        }else{
            $this->_credentials = $this->_params->get('google_credentials');
            $this->_token       = $this->_params->get('google_token');
            $this->connect();
        }


        if(!$this->_connected) {
            $this->connect();//en fonction de la date
        }

        try {
            $driveFile = $this->_drive->files->get($file->getDriveId(), ['alt' => 'media']);

            $path = $this->_params->get('tmp_files_dir').'/'.$file->getName().$file->getExtension();

            $fileSystem = new Filesystem();
            $fileSystem->remove([$path]);
            $fileSystem->dumpFile($path, $driveFile->getBody()->getContents());

            return $path;
        } catch (Google_Service_Exception $ex) {
            return null;
        }
    }
	
	public function getFile2(File $file2): ?string
    {
        if($this->_isdev) {
            return null;
        }

        //en fonction de la date "2025-02-12 19:19:49"
        // Date de référence
        $referenceDate = new DateTime("2025-02-12 19:19:49");

        // Récupération de la date du fichier
        $fileDate = ($file2->getDate());

        if ($fileDate > $referenceDate) {
            $this->_credentials = $this->_params->get('new_google_credentials');
            $this->_token       = $this->_params->get('new_google_token');
            $this->connect();
            
        }else{
            $this->_credentials = $this->_params->get('google_credentials');
            $this->_token       = $this->_params->get('google_token');
            $this->connect();
        }

        if(!$this->_connected) {
            $this->connect();
        }

        try {
            $driveFile2 = $this->_drive->files->get($file2->getDriveId(), ['alt' => 'media']);

            $path2 = $this->_params->get('tmp_files_dir').'/'.$file2->getName().$file2->getExtension();

            $fileSystem2 = new Filesystem();
            $fileSystem2->remove([$path]);
            $fileSystem2->dumpFile($path2, $driveFile2->getBody()->getContents());

            return $path2;
        } catch (Google_Service_Exception $ex) {
            return null;
        }
    }
public function getFilePath(File $file): ?string
    {
		/*if(!$this->_connected) {
            $this->connect();
        }*/
        try {
            //$driveFile = $this->_drive->files->get($file->getDriveId(), ['alt' => 'media']);

            $path = $this->_params->get('tmp_files_dir').'/'.$file->getName().$file->getExtension();

            //$fileSystem = new Filesystem();
            //$fileSystem->remove([$path]);
            //$fileSystem->dumpFile($path, $driveFile->getBody()->getContents());

           
            return $path;
        } catch (Google_Service_Exception $ex) {
            return null;
        }
    }
	
    public function listFiles()
    {
        if($this->_isdev) {
            return false;
        }

        if(!$this->_connected) {
            $this->connect();
        }

        $pageToken = null;
        $files = [];

        do {
            $results = $this->_drive->files->listFiles(['pageSize' => 1000, 'pageToken' => $pageToken, 'fields' => 'nextPageToken, files(id, name)']);
            foreach ($results->getFiles() as $file) {
                $files[] = $file;
            }

            if (!empty($results->getNextPageToken())) {
                $pageToken = $results->getNextPageToken();
            } else {
                $pageToken = null;
            }
        } while ($pageToken != null);

        if (count($files) == 0) {
            print "No files found.\n";
        } else {
            foreach ($files as $file) {
                printf("%s (%s) - \n", $file->getName(), $file->getId());
            }
            printf("Total: %s\n", count($files));
        }
    }

    public function renameFile(File $file, string $name): bool
    {
        if($this->_isdev) {
            return false;
        }

        if(!$this->_connected) {
            $this->connect();
        }

        try {
            $driveFile = $this->_drive->files->get($file->getDriveId());
            $driveFile->setName($name);
            $this->_drive->files->update($file->getDriveId(), $driveFile);

            return true;
        } catch (Google_Service_Exception $ex) {
            if ($ex->getCode() === 404) {
                return true;
            }

            return false;
        }
    }

    public function trashFile(File $file): bool
    {
        if($this->_isdev) {
            return false;
        }

        if(!$this->_connected) {
            $this->connect();
        }

        try {
            $driveFile = new Google_Service_Drive_DriveFile();
            $driveFile->setTrashed(true);

            $this->_drive->files->update($file->getDriveId(), $driveFile);

            return true;
        } catch (Google_Service_Exception $ex) {
            if ($ex->getCode() === 404) {
                return true;
            }

            return false;
        }
    }

    /**
     * Returns an authorized API client.
     * @return Google_Client the authorized client object
     *
     * @throws Google_Exception|Exception
     */
    private function getClient()
    {

        
        $client = new Google_Client();
        $client->setApplicationName('Viag2e');
        $client->setScopes([Google_Service_Drive::DRIVE,Google_Service_Drive::DRIVE_FILE, Google_Service_Drive::DRIVE_METADATA]);
        try {
			$client->setAuthConfig($this->_credentials);
			
		} catch (Exception $e) {
			exit("Erreur setAuthConfig: " . $e->getMessage().". ".$this->_credentials);
		}

        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');

        // Load previously authorized token from a file, if it exists.
        // The file token.json stores the user's access and refresh tokens, and is
        // created automatically when the authorization flow completes for the first
        // time.
        $tokenPath = $this->_token;
		
        if (file_exists($tokenPath)) {
            $accessToken = json_decode(file_get_contents($tokenPath), true);
            $client->setAccessToken($accessToken);
        }

        // If there is no previous token or it's expired.
        if ($client->isAccessTokenExpired()) {
            // Refresh the token if possible, else fetch a new one.
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            } else {
                // Request authorization from the user.
                $authUrl = $client->createAuthUrl();
                printf("Open the following link in your browser:\n%s\n", $authUrl);
                print 'Enter verification code: ';
                $authCode = trim(fgets(STDIN));

                // Exchange authorization code for an access token.
                $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
                $client->setAccessToken($accessToken);

				
				
				
                // Check to see if there was an error.
                if (array_key_exists('error', $accessToken)) {
                    throw new Exception(join(', ', $accessToken));
                }
            }
            // Save the token to a file.
            if (!file_exists(dirname($tokenPath))) {
                mkdir(dirname($tokenPath), 0700, true);
            }
            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
        }
        return $client;
    }

    static function dev_fake_id() {
        return 'dev' . bin2hex(random_bytes(10));
    }
}
