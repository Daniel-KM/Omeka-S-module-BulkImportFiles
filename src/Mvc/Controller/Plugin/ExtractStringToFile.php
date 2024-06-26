<?php declare(strict_types=1);

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

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\File\Exception\RuntimeException;

/**
 * Extract a string from a file of any size.
 */
class ExtractStringToFile extends AbstractPlugin
{
    /**
     * Extract a string to a file of any size from a start and a end strings.
     *
     * @link https://stackoverflow.com/questions/1578169/how-can-i-read-xmp-data-from-a-jpg-with-php#2837300
     *
     * @param string $filepath
     * @param string $startString
     * @param string $endString
     * @param string $chunkSize
     * @return string|null
     * @throws \Omeka\File\Exception\RuntimeException
     */
    public function __invoke($filepath, $content)
    {
        // $chunkSize = 131072;
        if (!strlen($filepath)) {
            throw new RuntimeException('Filepath string should be longer that zero character.');
        }

        if (($handle = fopen($filepath, 'w')) === false) {
            throw new RuntimeException(sprintf('Could not save file "%s" for reading/'), $filepath);
        }

        // $buffer = '';
        // $hasString = false;

        fwrite($handle, $content);
        fclose($handle);

        // return $hasString ? $buffer : null;
        return true;
    }
}
