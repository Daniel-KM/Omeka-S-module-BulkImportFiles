<?php

/*
 * Copyright 2019 Daniel Berthereau
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software. You can use, modify and/or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software’s author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user’s attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software’s suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace BulkImportFiles\Mvc\Controller\Plugin;

use Omeka\File\Exception\RuntimeException;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * Extract a string from a file of any size.
 */
class ExtractStringFromFile extends AbstractPlugin
{
    /**
     * Extract a string from a file of any size from a start and a end strings.
     *
     * @url https://stackoverflow.com/questions/1578169/how-can-i-read-xmp-data-from-a-jpg-with-php#2837300
     *
     * @param string $filepath
     * @param string $startString
     * @param string $endString
     * @param string $chunkSize
     * @return string|null
     * @throws \Omeka\File\Exception\RuntimeException
     */
    public function __invoke($filepath, $startString, $endString, $chunkSize = 131072)
    {
        if (!strlen($filepath) || !strlen($startString) || !strlen($endString)) {
            throw new RuntimeException('Filepath, start string and end string should be longer that zero character.');
        }

        $chunkSize = (int) $chunkSize;
        if ($chunkSize <= strlen($startString) || $chunkSize <= strlen($endString)) {
            throw new RuntimeException('Chunk size should be longer than start and end strings.');
        }

        if (($handle = fopen($filepath, 'r')) === false) {
            throw new RuntimeException(sprintf('Could not open file "%s" for reading/'), $filepath);
        }

        $buffer = '';
        $hasString = false;

        while (($chunk = fread($handle, $chunkSize)) !== false) {
            if ($chunk === '') {
                break;
            }

            $buffer .= $chunk;
            $startPosition = strpos($buffer, $startString);
            $endPosition = strpos($buffer, $endString);

            if ($startPosition !== false && $endPosition !== false) {
                $buffer = substr($buffer, $startPosition, $endPosition - $startPosition + 12);
                $hasString = true;
                break;
            } elseif ($startPosition !== false) {
                $buffer = substr($buffer, $startPosition);
                $hasString = true;
            } elseif (strlen($buffer) > (strlen($startString) * 2)) {
                $buffer = substr($buffer, strlen($startString));
            }
        }

        fclose($handle);

        return $hasString ? $buffer : null;
    }
}
