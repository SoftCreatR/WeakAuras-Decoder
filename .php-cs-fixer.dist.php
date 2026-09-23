<?php

/*
 * Copyright by SoftCreatR.dev.
 *
 * License: https://softcreatr.dev/license-terms
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * The above copyright notice and this disclaimer notice shall be included in all
 * copies or substantial portions of the Software.
 */

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PER-CS' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => false,
        ],
        'ordered_imports' => [
            'imports_order' => ['class', 'function', 'const'],
        ],
        'blank_line_before_statement' => [
            'statements' => [
                'break',
                'continue',
                'do',
                'for',
                'foreach',
                'if',
                'return',
                'switch',
                'throw',
                'try',
                'while',
            ],
        ],
    ])
    ->setFinder($finder);
