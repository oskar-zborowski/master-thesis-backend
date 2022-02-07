<?php

namespace App\Http\Libraries\FileProcessing;

use App\Exceptions\ApiException;
use App\Http\ErrorCodes\DefaultErrorCode;
use App\Http\Libraries\Encrypter\Encrypter;
use App\Http\Libraries\Validation\Validation;
use App\Models\Image;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Klasa przetwarzająca wgrywane pliki
 */
class FileProcessing
{
    /**
     * Proces zapisania pliku na serwerze
     * 
     * @param string $filePath ścieżka do pliku który ma zostać zapisany
     * @param string $folder katalog na dysku w którym ma zostać zapisany plik
     * @param mixed $entity encja której dotyczyć ma zapisywany plik
     * @param string $field nazwa pola w którym ma zostać zapisana nazwa pliku
     * @param bool $originalSource flaga określająca czy plik ma zostać zapisany bez żadnych modyfikacji
     * @param string|null $filename nazwa pliku pod jaką ma zostać zapisany plik
     * @param string|null $fileExtension rozszerzenie zapisanego pliku
     * 
     * @return void
     */
    private static function saveFile(string $filePath, string $folder, $entity, string $field, bool $originalSource, ?string $filename = null, ?string $fileExtension = null): void {

        if (!isset($fileExtension)) {
            $pathParts = pathinfo($filePath);
            $fileExtension = '.' . $pathParts['extension'];
        } else {
            $fileExtension = '.' . $fileExtension;
        }

        if (!isset($filename)) {
            $encrypter = new Encrypter;
            $filename = $encrypter->generateToken(64, $entity, $field, $fileExtension);
        } else {
            if (!Validation::checkUniqueness($filename, $entity, $field)) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    'Taka nazwa pliku jest już zajęta'
                );
            }
        }

        $fileContents = file_get_contents($filePath);
        $fileDestination = $folder . '/' . $filename;

        if ($originalSource) {
            Storage::put($fileDestination, $fileContents);
        } else {
            switch ($fileExtension) {
                case '.jpeg':
                    $uploadedImage = imagecreatefromstring($fileContents);
                    $imageWidth = imagesx($uploadedImage);
                    $imageHeight = imagesy($uploadedImage);
                    $newImage = imagecreatetruecolor($imageWidth, $imageHeight);
                    imagecopyresampled($newImage, $uploadedImage, 0, 0, 0, 0, $imageWidth, $imageHeight, $imageWidth, $imageHeight);
                    imagejpeg($newImage, 'storage/' . $fileDestination, 100); // TODO Potestować ile maksymalnie można zmniejszyć jakość obrazu, żeby nadal był akceptowalny
                    break;
            }
        }

        switch ($entity) {
            case 'avatar':
                /** @var Image $image */
                $image = new Image;
                $image->imageable_type = 'App\Models\User';
                $image->imageable_id = $user->id;
                $image->filename = $filename;
                $image->creator_id = $user->id;
                $image->visible_at = now();
                $image->save();
                $file = $image;
                break;
        }
    }

    /**
     * Proces zapisania zdjęcia profilowego na serwerze
     * 
     * @param string $avatarPath ścieżka do zdjęcia które ma zostać zapisane
     * @param bool $uploadedByForm flaga określająca czy plik został wgrany poprzez formularz
     * 
     * @return Image
     */
    public static function saveAvatar(string $avatarPath, bool $uploadedByForm): Image {
        return self::saveFile('avatar', $avatarPath, 'user-pictures', false, $uploadedByForm, null, 'jpeg');
    }
}
