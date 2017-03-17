<?php

namespace Oro\Bundle\TestFrameworkBundle\Behat\Element;

use Behat\Mink\Element\NodeElement;

class TableRow extends Element
{
    const HEADER_ELEMENT = 'TableHeader';

    /**
     * @param int $number Row index number starting from 0
     * @return NodeElement
     */
    public function getCellByNumber($number)
    {
        $number = (int) $number;
        $columns = $this->findAll('css', 'td');
        self::assertArrayHasKey($number, $columns);

        return $columns[$number];
    }

    /**
     * @param string $header Column header name
     * @return \DateTime|int|string
     */
    public function getCellValue($header)
    {
        /** @var TableHeader $tableHeader */
        $tableHeader = $this->elementFactory->createElement(static::HEADER_ELEMENT, $this->getParent()->getParent());
        $columnNumber = $tableHeader->getColumnNumber($header);

        return $this->normalizeValueByGuessingType(
            $this->getCellByNumber($columnNumber)->getText()
        );
    }

    /**
     * Try to guess type of value and return that data in that type
     * @param string $value
     * @return \DateTime|int|string
     */
    protected function normalizeValueByGuessingType($value)
    {
        $value = trim($value);

        if (preg_match('/^[0-9]+$/', $value)) {
            return (int) $value;
        } elseif (preg_match('/^\p{Sc}(?P<amount>[0-9]+)$/', $value, $matches)) {
            return (int) $matches['amount'];
        } elseif ($date = date_create($value)) {
            return $date;
        }

        return $value;
    }
}
