<?php
/**
 * Copyright © 2023 Lingaro sp. z o.o. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Lingaro\Magento2CodingStandardXml\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * @see \PHP_CodeSniffer\Standards\Squiz\Sniffs\Commenting\FileCommentSniff
 */
class FileCommentSniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var string[]
     */
    public array $supportedTokenizers = [
        'PHP'
    ];

    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return int[]
     */
    public function register()
    {
        return [T_OPEN_TAG];
    }

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  The position of the current token in the stack passed in $tokens.
     * @return int
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        $commentStart = $phpcsFile->findNext(T_INLINE_HTML, $stackPtr + 1);
        if ($commentStart === false || $tokens[$commentStart]['content'] !== "<!--\n") {
            $phpcsFile->addError('Missing file doc comment', $stackPtr, 'Missing');
            $phpcsFile->recordMetric($stackPtr, 'File has doc comment', 'no');
            return ($phpcsFile->numTokens + 1);
        }

        if (!isset($tokens[$commentStart + 1])
            || !isset($tokens[$commentStart + 2])
            || !isset($tokens[$commentStart + 3])
            || !isset($tokens[$commentStart + 4])
            || !isset($tokens[$commentStart + 5])
            || $tokens[$commentStart + 1]['content'] !== "/**\n"
            || strpos($tokens[$commentStart + 2]['content'], ' * ') !== 0
            || strpos($tokens[$commentStart + 3]['content'], ' * ') !== 0
            || $tokens[$commentStart + 4]['content'] !== " */\n"
            || $tokens[$commentStart + 5]['content'] !== "-->\n"
        ) {
            $phpcsFile->addError('You must use "/**" style comments inside "<!--" comment tags for a file comment', $commentStart, 'WrongStyle');
            $phpcsFile->recordMetric($stackPtr, 'File has doc comment', 'yes');
            return ($phpcsFile->numTokens + 1);
        }

        if (strpos($tokens[$commentStart + 2]['content'], '* Copyright ©') === false) {
            $error = 'Expected copyright declaration "Copyright ©" in first line of comment';
            $phpcsFile->addError($error, $commentStart + 2, 'MissingCopyrightTag');
        } elseif (preg_match('/^ \* Copyright © ([0-9]{4})(-[0-9]{4})? (.*)\. All rights reserved\.\n$/', $tokens[$commentStart + 2]['content']) === 0) {
            $error = 'Expected "Copyright © <DATE> <COMPANY>. All rights reserved." for copyright declaration';
            $phpcsFile->addError($error, $commentStart + 2, 'IncorrectCopyright');
        }

        if (
            strpos($tokens[$commentStart + 3]['content'], '* See LICENSE for license details.') === false
            || trim($tokens[$commentStart + 3]['content']) !== '* See LICENSE for license details.'
        ) {
            $error = 'Expected "See LICENSE for license details." in second line of comment';
            $phpcsFile->addError($error, $commentStart + 3, 'MissingCopyrightTag');
        }

        return ($phpcsFile->numTokens + 1);
    }
}
