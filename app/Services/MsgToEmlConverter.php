<?php

namespace App\Services;

use Hfig\MAPI\OLE\Pear\DocumentFactory;
use Hfig\MAPI\MapiMessageFactory;
use Hfig\MAPI\Message\SwiftMailer\MessageFactory;
use Illuminate\Support\Str;

class MsgToEmlConverter
{
    public static function convert(string $msgPath): string
    {
        if (!file_exists($msgPath)) {
            throw new \RuntimeException('MSG-Datei nicht gefunden');
        }

        $outputDir = storage_path('app/tmp_eml');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0775, true);
        }

        $emlPath = $outputDir . '/' . Str::uuid() . '.eml';

        try {
            // 1️⃣ MSG (OLE) laden
            $ole = (new DocumentFactory())->createFromFile($msgPath);

            // 2️⃣ MAPI Message erzeugen
            $mapiMessage = (new MapiMessageFactory())->parseMessage($ole);

            // 3️⃣ MAPI → MIME (SwiftMailer)
            $factory = new MessageFactory();
            $swiftMessage = $factory->create($mapiMessage);

            // 4️⃣ Als .eml speichern
            file_put_contents($emlPath, $swiftMessage->toString());

        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'MSG → EML Konvertierung fehlgeschlagen: ' . $e->getMessage(),
                previous: $e
            );
        }

        if (!file_exists($emlPath) || filesize($emlPath) === 0) {
            throw new \RuntimeException('EML-Datei konnte nicht erzeugt werden');
        }

        return $emlPath;
    }
}
