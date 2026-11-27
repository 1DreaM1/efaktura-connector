<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron;

use DOMDocument;
use DOMElement;
use DOMNode;
use DreaM1\EfakturaConnector\Core\Ubl\UblAttributeName;
use DreaM1\EfakturaConnector\Core\Ubl\UblElementName;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolValidationAdjustment;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolValidationIssue;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolValidationLine;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationSeverity;
use DreaM1\EfakturaConnector\Core\Xml\XmlBooleanValue;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\ValidationRuleGuidanceCatalog;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\Reader\UblXPath;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\UblXPathExpressionBuilder;

/** @internal Adds UBL line context and package guidance to one parsed Schematron assertion. */
final class UblSchematronIssueFactory
{
    private UblXPath $xpath;
    /** @var DOMNode[]|null Source document lines, looked up on first line-level finding. */
    private ?array $sourceLines = null;

    public function __construct(DOMDocument $source)
    {
        $this->xpath = new UblXPath($source);
    }

    public function create(
        string $ruleId,
        string $message,
        string $location,
        UblValidationSeverity $severity
    ): PeppolValidationIssue {
        return new PeppolValidationIssue(
            $ruleId,
            $message,
            $location,
            $this->lineForLocation($location),
            $severity,
            ValidationRuleGuidanceCatalog::forRule($ruleId)
        );
    }

    private function lineForLocation(string $location): ?PeppolValidationLine
    {
        $lineIndex = $this->lineIndexForLocation($location);

        if ($lineIndex === null) {
            return null;
        }

        if ($this->sourceLines === null) {
            $this->sourceLines = $this->xpath->queryNodes(
                '//' . UblXPathExpressionBuilder::cac(UblElementName::INVOICE_LINE)
                . ' | //' . UblXPathExpressionBuilder::cac(UblElementName::CREDIT_NOTE_LINE)
            );
        }

        $line = $this->sourceLines[$lineIndex - 1] ?? null;

        return $line instanceof DOMElement ? $this->lineFromElement($line) : null;
    }

    private function lineIndexForLocation(string $location): ?int
    {
        $linePattern = '/\b(?:'
            . preg_quote(UblElementName::INVOICE_LINE, '/')
            . '|'
            . preg_quote(UblElementName::CREDIT_NOTE_LINE, '/')
            . ')\[(\d+)]/';

        return preg_match($linePattern, $location, $matches) === 1
            ? (int) $matches[1]
            : null;
    }

    private function lineFromElement(DOMElement $line): PeppolValidationLine
    {
        $name = $this->xpath->nodeValue($line, UblXPathExpressionBuilder::path(
            UblXPathExpressionBuilder::cac(UblElementName::ITEM),
            UblXPathExpressionBuilder::cbc(UblElementName::NAME)
        )) ?? '';
        $identifier = $this->xpath->nodeValue($line, UblXPathExpressionBuilder::cbc(UblElementName::ID)) ?? '';
        $quantity   = $this->xpath->nodeValue(
            $line,
            UblXPathExpressionBuilder::cbc(UblElementName::INVOICED_QUANTITY)
                . ' | ' . UblXPathExpressionBuilder::cbc(UblElementName::CREDITED_QUANTITY)
        ) ?? '';
        $priceAmount = $this->xpath->nodeValue($line, UblXPathExpressionBuilder::path(
            UblXPathExpressionBuilder::cac(UblElementName::PRICE),
            UblXPathExpressionBuilder::cbc(UblElementName::PRICE_AMOUNT)
        )) ?? '';
        $baseQuantity = $this->xpath->nodeValue($line, UblXPathExpressionBuilder::path(
            UblXPathExpressionBuilder::cac(UblElementName::PRICE),
            UblXPathExpressionBuilder::cbc(UblElementName::BASE_QUANTITY)
        )) ?? '';
        $lineAmount = $this->xpath->nodeValue(
            $line,
            UblXPathExpressionBuilder::cbc(UblElementName::LINE_EXTENSION_AMOUNT)
        ) ?? '';
        $lineCurrency = $this->xpath->nodeValue(
            $line,
            UblXPathExpressionBuilder::cbc(UblElementName::LINE_EXTENSION_AMOUNT)
                . '/@' . UblAttributeName::CURRENCY_ID
        ) ?? '';
        $documentCurrency = $this->xpath->value(
            UblXPathExpressionBuilder::cbc(UblElementName::DOCUMENT_CURRENCY_CODE)
        ) ?? '';

        return new PeppolValidationLine(
            $name,
            $identifier,
            $quantity,
            $priceAmount,
            $baseQuantity,
            $lineAmount,
            $lineCurrency ?: $documentCurrency,
            $this->lineAdjustments($line)
        );
    }

    /** @return PeppolValidationAdjustment[] */
    private function lineAdjustments(DOMElement $line): array
    {
        $adjustments = [];

        foreach ($this->xpath->queryNodes(UblXPathExpressionBuilder::cac(UblElementName::ALLOWANCE_CHARGE), $line) as $adjustment) {
            $amount          = $this->xpath->nodeValue($adjustment, UblXPathExpressionBuilder::cbc(UblElementName::AMOUNT)) ?? '';
            $chargeIndicator = $this->xpath->nodeValue(
                $adjustment,
                UblXPathExpressionBuilder::cbc(UblElementName::CHARGE_INDICATOR)
            ) ?? '';
            $adjustments[] = new PeppolValidationAdjustment(
                $amount,
                strtolower($chargeIndicator) === XmlBooleanValue::TRUE
            );
        }

        return $adjustments;
    }
}
