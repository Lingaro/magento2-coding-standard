<?php
/**
 * Copyright © 2023 Lingaro sp. z o.o. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Lingaro\Magento2CodingStandard\Sniffs\Commenting;

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
        $tokens       = $phpcsFile->getTokens();
        $commentStart = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);

        if ($tokens[$commentStart]['code'] === T_COMMENT) {
            $phpcsFile->addError('You must use "/**" style comments for a file comment', $commentStart, 'WrongStyle');
            $phpcsFile->recordMetric($stackPtr, 'File has doc comment', 'yes');
            return ($phpcsFile->numTokens + 1);
        } else if ($commentStart === false || $tokens[$commentStart]['code'] !== T_DOC_COMMENT_OPEN_TAG) {
            $phpcsFile->addError('Missing file doc comment', $stackPtr, 'Missing');
            $phpcsFile->recordMetric($stackPtr, 'File has doc comment', 'no');
            return ($phpcsFile->numTokens + 1);
        }

        if (isset($tokens[$commentStart]['comment_closer']) === false
            || ($tokens[$tokens[$commentStart]['comment_closer']]['content'] === ''
            && $tokens[$commentStart]['comment_closer'] === ($phpcsFile->numTokens - 1))
        ) {
            // Don't process an unfinished file comment during live coding.
            return ($phpcsFile->numTokens + 1);
        }

        $commentEnd = $tokens[$commentStart]['comment_closer'];

        $nextToken = $phpcsFile->findNext(
            T_WHITESPACE,
            ($commentEnd + 1),
            null,
            true
        );

        $ignore = [
            T_CLASS,
            T_INTERFACE,
            T_TRAIT,
            T_FUNCTION,
            T_CLOSURE,
            T_PUBLIC,
            T_PRIVATE,
            T_PROTECTED,
            T_FINAL,
            T_STATIC,
            T_ABSTRACT,
            T_CONST,
            T_PROPERTY,
            T_INCLUDE,
            T_INCLUDE_ONCE,
            T_REQUIRE,
            T_REQUIRE_ONCE,
        ];

        if (in_array($tokens[$nextToken]['code'], $ignore, true) === true) {
            $phpcsFile->addError('Missing file doc comment', $stackPtr, 'Missing');
            $phpcsFile->recordMetric($stackPtr, 'File has doc comment', 'no');
            return ($phpcsFile->numTokens + 1);
        }

        $phpcsFile->recordMetric($stackPtr, 'File has doc comment', 'yes');

        // Exactly one blank line before the file comment.
        if ($tokens[$commentStart]['line'] > ($tokens[$stackPtr]['line'] + 2)) {
            $error = 'There must be exactly one blank line before the file comment';
            $phpcsFile->addError($error, $stackPtr, 'SpacingAfterOpen');
        }

        // Exactly one blank line after the file comment.
        $next = $phpcsFile->findNext(T_WHITESPACE, ($commentEnd + 1), null, true);
        if ($tokens[$next]['line'] !== ($tokens[$commentEnd]['line'] + 2)) {
            $error = 'There must be exactly one blank line after the file comment';
            $phpcsFile->addError($error, $commentEnd, 'SpacingAfterComment');
        }

        $key = $commentStart;
        $copyrightMissed = true;
        while ($key < $commentEnd) {
            $firstStringKey = $phpcsFile->findNext(T_DOC_COMMENT_STRING, $key, $commentEnd);
            if ($firstStringKey === false) {
                break;
            }
            $key = $firstStringKey + 1;

            $tokenCopyright = $tokens[$firstStringKey];
            $isCopyrightInString = strpos($tokenCopyright['content'], 'Copyright ©') !== false;

            if ($isCopyrightInString) {
                $copyrightMissed = false;

                $isLingaroNamespace = false;
                $namespaceStart = $phpcsFile->findNext(T_NAMESPACE, $stackPtr + 1);
                if ($namespaceStart !== false) {
                    $isLingaroNamespace = $tokens[$namespaceStart + 2]['content'] === 'Lingaro';
                }
                if ($isLingaroNamespace) {
                    if (preg_match('/^Copyright © ([0-9]{4})(-[0-9]{4})? Lingaro Sp\. z o\.o\. All rights reserved\.$/', $tokenCopyright['content']) === 0) {
                        $error = 'Expected "Copyright © <DATE> Lingaro Sp. z o.o. All rights reserved." for copyright declaration';
                        $fix = $phpcsFile->addFixableError($error, $firstStringKey, 'IncorrectCopyright');
                        if ($fix === true) {
                            $matches = [];
                            preg_match('/^Copyright © ([0-9]{4})(-[0-9]{4})? (Lingaro). All rights reserved\.$/', $tokenCopyright['content'], $matches);
                            if (isset($matches[1]) === false) {
                                $matches[1] = date('Y');
                            }

                            $expected = 'Copyright © ' . $matches[1] . ' Lingaro Sp. z o.o. All rights reserved.';
                            $phpcsFile->fixer->replaceToken($firstStringKey, $expected);
                        }
                    }
                } else {
                    if (preg_match('/^Copyright © ([0-9]{4})(-[0-9]{4})? (.*)\. All rights reserved\.$/', $tokenCopyright['content']) === 0) {
                        $error = 'Expected "Copyright © <DATE> <COMPANY>. All rights reserved." for copyright declaration.';
                        $phpcsFile->addError($error, $firstStringKey, 'IncorrectCopyright');
                    }
                }

                $secondStringKey = $phpcsFile->findNext(T_DOC_COMMENT_STRING, $key, $commentEnd);
                $tokenLicenseStr = $tokens[$secondStringKey];
                $expectedLicense = 'See LICENSE for license details.';

                if ($secondStringKey === false || trim($tokenLicenseStr['content']) !== trim($expectedLicense)) {
                    $error = 'Expected "' . $expectedLicense . '" for copyright declaration';
                    $phpcsFile->addError($error, $firstStringKey + 2, 'IncorrectLicenseText');
                    break;
                }
            }
        }

        if ($copyrightMissed) {
            $firstStarAfterCommentStartKey = $phpcsFile->findNext(T_DOC_COMMENT_STAR, ($stackPtr + 1), $commentEnd);
            $error = 'Expected "Copyright © <DATE> <COMPANY>. All rights reserved." for copyright declaration.';
            $phpcsFile->addError($error, $firstStarAfterCommentStartKey, 'MissedCopyright');
        }

        // Ignore the rest of the file.
        return ($phpcsFile->numTokens + 1);

    }
}
