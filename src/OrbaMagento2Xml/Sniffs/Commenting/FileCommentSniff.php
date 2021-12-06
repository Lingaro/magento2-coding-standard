<?php

/**
 * @copyright Copyright © 2021 Orba. All rights reserved.
 * @author    info@orba.co
 */

declare(strict_types=1);

namespace Orba\Magento2CodingStandardXml\Sniffs\Commenting;

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

        if (strpos($tokens[$commentStart + 2]['content'], ' * @copyright') !== 0) {
            $error = 'Expected copyright declaration in first line of comment';
            $phpcsFile->addError($error, $commentStart + 2, 'MissingCopyrightTag');
        } elseif (preg_match('/^ \* @copyright(\s*)Copyright © ([0-9]{4})(-[0-9]{4})? (.*)\. All rights reserved\.\n$/', $tokens[$commentStart + 2]['content']) === 0) {
            $error = 'Expected "Copyright © <DATE> <COMPANY>. All rights reserved." for copyright declaration';
            $phpcsFile->addError($error, $commentStart + 2, 'IncorrectCopyright');
        }

        if (strpos($tokens[$commentStart + 3]['content'], ' * @author') !== 0) {
            $error = 'Expected author declaration in second line of comment';
            $phpcsFile->addError($error, $commentStart + 3, 'MissingCopyrightTag');
        } elseif (preg_match('/^ \* @author(\s*)info@orba.co\n$/', $tokens[$commentStart + 3]['content']) === 0) {
            $error = 'Expected "info@orba.co" for author tag';
            $fix   = $phpcsFile->addFixableError($error, $commentStart + 3, 'IncorrectAuthor');
            if ($fix === true) {
                $expected = " * @author    info@orba.co\n";
                $phpcsFile->fixer->replaceToken($commentStart + 3, $expected);
            }
        }

        return ($phpcsFile->numTokens + 1);
    }
}
