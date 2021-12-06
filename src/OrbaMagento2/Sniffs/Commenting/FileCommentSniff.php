<?php

/**
 * @copyright Copyright © 2021 Orba. All rights reserved.
 * @author    info@orba.co
 */

declare(strict_types=1);

namespace Orba\Magento2CodingStandard\Sniffs\Commenting;

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

        // Required tags in correct order.
        $required = [
            '@copyright'  => true,
            '@author'     => true,
        ];

        $foundTags = [];
        foreach ($tokens[$commentStart]['comment_tags'] as $tag) {
            $name       = $tokens[$tag]['content'];
            $isRequired = isset($required[$name]);

            if ($isRequired === true && in_array($name, $foundTags, true) === true) {
                $error = 'Only one %s tag is allowed in a file comment';
                $data  = [$name];
                $phpcsFile->addError($error, $tag, 'Duplicate'.ucfirst(substr($name, 1)).'Tag', $data);
            }

            $foundTags[] = $name;

            if ($isRequired === false) {
                continue;
            }

            $string = $phpcsFile->findNext(T_DOC_COMMENT_STRING, $tag, $commentEnd);
            if ($string === false || $tokens[$string]['line'] !== $tokens[$tag]['line']) {
                $error = 'Content missing for %s tag in file comment';
                $data  = [$name];
                $phpcsFile->addError($error, $tag, 'Empty'.ucfirst(substr($name, 1)).'Tag', $data);
                continue;
            }

            if ($name === '@author') {
                if ($tokens[$string]['content'] !== 'info@orba.co') {
                    $error = 'Expected "info@orba.co" for author tag';
                    $fix   = $phpcsFile->addFixableError($error, $tag, 'IncorrectAuthor');
                    if ($fix === true) {
                        $expected = 'info@orba.co';
                        $phpcsFile->fixer->replaceToken($string, $expected);
                    }
                }
            } else if ($name === '@copyright') {
                $isOrbaNamespace = false;
                $namespaceStart = $phpcsFile->findNext(T_NAMESPACE, $stackPtr + 1);
                if ($namespaceStart !== false) {
                    $isOrbaNamespace = $tokens[$namespaceStart + 2]['content'] === 'Orba';
                }
                if ($isOrbaNamespace) {
                    if (preg_match('/^Copyright © ([0-9]{4})(-[0-9]{4})? (Orba). All rights reserved.$/', $tokens[$string]['content']) === 0) {
                        $error = 'Expected "Copyright © <DATE> Orba. All rights reserved." for copyright declaration';
                        $fix   = $phpcsFile->addFixableError($error, $tag, 'IncorrectCopyright');
                        if ($fix === true) {
                            $matches = [];
                            preg_match('/^Copyright © ([0-9]{4})(-[0-9]{4})? (Orba). All rights reserved.$/', $tokens[$string]['content'], $matches);
                            if (isset($matches[1]) === false) {
                                $matches[1] = date('Y');
                            }

                            $expected = 'Copyright © ' . $matches[1] . ' Orba. All rights reserved.';
                            $phpcsFile->fixer->replaceToken($string, $expected);
                        }
                    }
                } else {
                    if (preg_match('/^Copyright © ([0-9]{4})(-[0-9]{4})? (.*). All rights reserved.$/', $tokens[$string]['content']) === 0) {
                        $error = 'Expected "Copyright © <DATE> <COMPANY>. All rights reserved." for copyright declaration';
                        $phpcsFile->addError($error, $tag, 'IncorrectCopyright');
                    }
                }
            }//end if
        }//end foreach

        // Check if the tags are in the correct position.
        $pos = 0;
        foreach ($required as $tag => $true) {
            if (in_array($tag, $foundTags, true) === false) {
                $error = 'Missing %s tag in file comment';
                $data  = [$tag];
                $phpcsFile->addError($error, $commentEnd, 'Missing'.ucfirst(substr($tag, 1)).'Tag', $data);
            }

            if (isset($foundTags[$pos]) === false) {
                break;
            }

            if ($foundTags[$pos] !== $tag) {
                $error = 'The tag in position %s should be the %s tag';
                $data  = [
                    ($pos + 1),
                    $tag,
                ];
                $phpcsFile->addError($error, $tokens[$commentStart]['comment_tags'][$pos], ucfirst(substr($tag, 1)).'TagOrder', $data);
            }

            $pos++;
        }

        // Ignore the rest of the file.
        return ($phpcsFile->numTokens + 1);

    }
}
