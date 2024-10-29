<?php

declare(strict_types=1);

namespace Ssch\TYPO3Rector\TYPO313\v3;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Type\ObjectType;
use Rector\PhpParser\Node\Value\ValueResolver;
use Rector\Rector\AbstractRector;
use Ssch\TYPO3Rector\Contract\FilesystemInterface;
use Ssch\TYPO3Rector\Filesystem\FilesFinder;
use Ssch\TYPO3Rector\Helper\ArrayUtility;
use Ssch\TYPO3Rector\Helper\ExtensionKeyResolverTrait;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeTraverser;

/**
 * @changelog https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/13.3/Deprecation-104778-InstantiationOfIconRegistryInExtLocalconf.html
 */
final class MoveIconRegistrationToConfigurationIconsRector extends AbstractRector
{
    private ValueResolver $valueResolver;
    private FilesFinder $filesFinder;
    private FilesystemInterface $filesystem;

    public function __construct(
        ValueResolver $valueResolver,
        FilesFinder $filesFinder,
        FilesystemInterface $filesystem
    ){
        $this->valueResolver = $valueResolver;
        $this->filesFinder = $filesFinder;
        $this->filesystem = $filesystem;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Move IconRegistry::registerIcon() from ext_localconf.php to Configuration/Icons.php', [new CodeSample(
            <<<'CODE_SAMPLE'
$iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
  \TYPO3\CMS\Core\Imaging\IconRegistry::class,
);
$iconRegistry->registerIcon(
    'example',
    \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
    [
        'source' => 'EXT:example/Resources/Public/Icons/example.svg'
    ],
);
CODE_SAMPLE
            ,
            <<<'CODE_SAMPLE'
// Move to file Configuration/Icons.php
CODE_SAMPLE
        )]);
    }

    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    /**
     * @param MethodCall $node
     */
    public function refactor(Node $node): ?int
    {
        if (!$this->filesFinder->isExtLocalConf($this->file->getFilePath())) {
            return null;
        }

        $expr = $node->expr;

        // Remove variable declaration of $iconRegistry
        if ($expr instanceof Assign
            && $expr->expr instanceof StaticCall
            && (string)$expr->expr->class === 'TYPO3\CMS\Core\Utility\GeneralUtility'
            && $expr->expr->name->name === 'makeInstance'
            && $this->valueResolver->getValue($expr->expr->args[0]) === 'TYPO3\CMS\Core\Imaging\IconRegistry'
        ) {
            return NodeTraverser::REMOVE_NODE;
        }

        if (!$expr instanceof MethodCall) {
            return null;
        }
        if (!$expr->name->name === 'registerIcon') {
            return null;
        }

        $iconIdentifier = $this->valueResolver->getValue($expr->args[0]);
        $iconProvider = $this->valueResolver->getValue($expr->args[1]);
        $iconSource = $this->valueResolver->getValue($expr->args[2])['source'];

        $directoryName = dirname($this->file->getFilePath());
        $newConfigurationFile = $directoryName . '/Configuration/Icons.php';
        $icons = $this->filesystem->fileExists($newConfigurationFile) ? require $newConfigurationFile : [];
        $icons[$iconIdentifier] = [
            'provider' => $iconProvider,
            'source' => $iconSource,
        ];
        $content = ArrayUtility::arrayExport($icons);
        $this->filesystem->write($newConfigurationFile, <<<CODE
<?php

return {$content};

CODE);
        return NodeTraverser::REMOVE_NODE;
    }
}
