<?php

namespace DreaM1\EfakturaConnector\Core\Ubl;

/** UBL XML element local names used by the document reader and writer. */
final class UblElementName
{
    /** UBL XML element local name 'AccountingCustomerParty'. */
    public const ACCOUNTING_CUSTOMER_PARTY = 'AccountingCustomerParty';
    /** UBL XML element local name 'AccountingSupplierParty'. */
    public const ACCOUNTING_SUPPLIER_PARTY = 'AccountingSupplierParty';
    /** UBL XML element local name 'AccountingCost'. */
    public const ACCOUNTING_COST = 'AccountingCost';
    /** UBL XML element local name 'AdditionalItemProperty'. */
    public const ADDITIONAL_ITEM_PROPERTY = 'AdditionalItemProperty';
    /** UBL XML element local name 'AdditionalStreetName'. */
    public const ADDITIONAL_STREET_NAME = 'AdditionalStreetName';
    /** UBL XML element local name 'ActualDeliveryDate'. */
    public const ACTUAL_DELIVERY_DATE = 'ActualDeliveryDate';
    /** UBL XML element local name 'AllowanceCharge'. */
    public const ALLOWANCE_CHARGE = 'AllowanceCharge';
    /** UBL XML element local name 'AllowanceChargeReason'. */
    public const ALLOWANCE_CHARGE_REASON = 'AllowanceChargeReason';
    /** UBL XML element local name 'AllowanceChargeReasonCode'. */
    public const ALLOWANCE_CHARGE_REASON_CODE = 'AllowanceChargeReasonCode';
    /** UBL XML element local name 'AllowanceTotalAmount'. */
    public const ALLOWANCE_TOTAL_AMOUNT = 'AllowanceTotalAmount';
    /** UBL XML element local name 'Amount'. */
    public const AMOUNT = 'Amount';
    /** UBL XML element local name 'Address'. */
    public const ADDRESS = 'Address';
    /** UBL XML element local name 'AddressLine'. */
    public const ADDRESS_LINE = 'AddressLine';
    /** UBL XML element local name 'Attachment'. */
    public const ATTACHMENT = 'Attachment';
    /** UBL XML element local name 'BaseQuantity'. */
    public const BASE_QUANTITY = 'BaseQuantity';
    /** UBL XML element local name 'BaseAmount'. */
    public const BASE_AMOUNT = 'BaseAmount';
    /** UBL XML element local name 'BillingReference'. */
    public const BILLING_REFERENCE = 'BillingReference';
    /** UBL XML element local name 'BuildingNumber'. */
    public const BUILDING_NUMBER = 'BuildingNumber';
    /** UBL XML element local name 'BuyerReference'. */
    public const BUYER_REFERENCE = 'BuyerReference';
    /** UBL XML element local name 'BuyersItemIdentification'. */
    public const BUYERS_ITEM_IDENTIFICATION = 'BuyersItemIdentification';
    /** UBL XML element local name 'ChargeIndicator'. */
    public const CHARGE_INDICATOR = 'ChargeIndicator';
    /** UBL XML element local name 'CardAccount'. */
    public const CARD_ACCOUNT = 'CardAccount';
    /** UBL XML element local name 'ChargeTotalAmount'. */
    public const CHARGE_TOTAL_AMOUNT = 'ChargeTotalAmount';
    /** UBL XML element local name 'CityName'. */
    public const CITY_NAME = 'CityName';
    /** UBL XML element local name 'CommodityClassification'. */
    public const COMMODITY_CLASSIFICATION = 'CommodityClassification';
    /** UBL XML element local name 'ClassifiedTaxCategory'. */
    public const CLASSIFIED_TAX_CATEGORY = 'ClassifiedTaxCategory';
    /** UBL XML element local name 'CompanyID'. */
    public const COMPANY_ID = 'CompanyID';
    /** UBL XML element local name 'CompanyLegalForm'. */
    public const COMPANY_LEGAL_FORM = 'CompanyLegalForm';
    /** UBL XML element local name 'Country'. */
    public const COUNTRY = 'Country';
    /** UBL XML element local name 'CountrySubentity'. */
    public const COUNTRY_SUBENTITY = 'CountrySubentity';
    /** UBL XML element local name 'Contact'. */
    public const CONTACT = 'Contact';
    /** UBL XML element local name 'CreditNoteLine'. */
    public const CREDIT_NOTE_LINE = 'CreditNoteLine';
    /** UBL XML element local name 'CreditNoteTypeCode'. */
    public const CREDIT_NOTE_TYPE_CODE = 'CreditNoteTypeCode';
    /** UBL XML element local name 'CreditedQuantity'. */
    public const CREDITED_QUANTITY = 'CreditedQuantity';
    /** UBL XML element local name 'CustomizationID'. */
    public const CUSTOMIZATION_ID = 'CustomizationID';
    /** UBL XML element local name 'Delivery'. */
    public const DELIVERY = 'Delivery';
    /** UBL XML element local name 'Description'. */
    public const DESCRIPTION = 'Description';
    /** UBL XML element local name 'DescriptionCode'. */
    public const DESCRIPTION_CODE = 'DescriptionCode';
    /** UBL XML element local name 'DeliveryLocation'. */
    public const DELIVERY_LOCATION = 'DeliveryLocation';
    /** UBL XML element local name 'DeliveryParty'. */
    public const DELIVERY_PARTY = 'DeliveryParty';
    /** UBL XML element local name 'DocumentDescription'. */
    public const DOCUMENT_DESCRIPTION = 'DocumentDescription';
    /** UBL XML element local name 'DocumentReference'. */
    public const DOCUMENT_REFERENCE = 'DocumentReference';
    /** UBL XML element local name 'DocumentTypeCode'. */
    public const DOCUMENT_TYPE_CODE = 'DocumentTypeCode';
    /** UBL XML element local name 'DocumentCurrencyCode'. */
    public const DOCUMENT_CURRENCY_CODE = 'DocumentCurrencyCode';
    /** UBL XML element local name 'DueDate'. */
    public const DUE_DATE = 'DueDate';
    /** UBL XML element local name 'EndpointID'. */
    public const ENDPOINT_ID = 'EndpointID';
    /** UBL XML element local name 'EmbeddedDocumentBinaryObject'. */
    public const EMBEDDED_DOCUMENT_BINARY_OBJECT = 'EmbeddedDocumentBinaryObject';
    /** UBL XML element local name 'EndDate'. */
    public const END_DATE = 'EndDate';
    /** UBL XML element local name 'ElectronicMail'. */
    public const ELECTRONIC_MAIL = 'ElectronicMail';
    /** UBL XML element local name 'ExternalReference'. */
    public const EXTERNAL_REFERENCE = 'ExternalReference';
    /** UBL XML element local name 'FinancialInstitutionBranch'. */
    public const FINANCIAL_INSTITUTION_BRANCH = 'FinancialInstitutionBranch';
    /** UBL XML element local name 'HolderName'. */
    public const HOLDER_NAME = 'HolderName';
    /** UBL XML element local name 'ID'. */
    public const ID = 'ID';
    /** UBL XML element local name 'IdentificationCode'. */
    public const IDENTIFICATION_CODE = 'IdentificationCode';
    /** UBL XML element local name 'InvoiceDocumentReference'. */
    public const INVOICE_DOCUMENT_REFERENCE = 'InvoiceDocumentReference';
    /** UBL XML element local name 'InvoiceLine'. */
    public const INVOICE_LINE = 'InvoiceLine';
    /** UBL XML element local name 'InvoiceTypeCode'. */
    public const INVOICE_TYPE_CODE = 'InvoiceTypeCode';
    /** UBL XML element local name 'InvoicePeriod'. */
    public const INVOICE_PERIOD = 'InvoicePeriod';
    /** UBL XML element local name 'InvoicedQuantity'. */
    public const INVOICED_QUANTITY = 'InvoicedQuantity';
    /** UBL XML element local name 'Item'. */
    public const ITEM = 'Item';
    /** UBL XML element local name 'ItemClassificationCode'. */
    public const ITEM_CLASSIFICATION_CODE = 'ItemClassificationCode';
    /** UBL XML element local name 'IssueDate'. */
    public const ISSUE_DATE = 'IssueDate';
    /** UBL XML element local name 'LegalMonetaryTotal'. */
    public const LEGAL_MONETARY_TOTAL = 'LegalMonetaryTotal';
    /** UBL XML element local name 'LineExtensionAmount'. */
    public const LINE_EXTENSION_AMOUNT = 'LineExtensionAmount';
    /** UBL XML element local name 'Line'. */
    public const LINE = 'Line';
    /** UBL XML element local name 'LineID'. */
    public const LINE_ID = 'LineID';
    /** UBL XML element local name 'MultiplierFactorNumeric'. */
    public const MULTIPLIER_FACTOR_NUMERIC = 'MultiplierFactorNumeric';
    /** UBL XML element local name 'Name'. */
    public const NAME = 'Name';
    /** UBL XML element local name 'NetworkID'. */
    public const NETWORK_ID = 'NetworkID';
    /** UBL XML element local name 'Note'. */
    public const NOTE = 'Note';
    /** UBL XML element local name 'OrderReference'. */
    public const ORDER_REFERENCE = 'OrderReference';
    /** UBL XML element local name 'OrderLineReference'. */
    public const ORDER_LINE_REFERENCE = 'OrderLineReference';
    /** UBL XML element local name 'OriginCountry'. */
    public const ORIGIN_COUNTRY = 'OriginCountry';
    /** UBL XML element local name 'PayableAmount'. */
    public const PAYABLE_AMOUNT = 'PayableAmount';
    /** UBL XML element local name 'PayableRoundingAmount'. */
    public const PAYABLE_ROUNDING_AMOUNT = 'PayableRoundingAmount';
    /** UBL XML element local name 'PayeeFinancialAccount'. */
    public const PAYEE_FINANCIAL_ACCOUNT = 'PayeeFinancialAccount';
    /** UBL XML element local name 'PayeeParty'. */
    public const PAYEE_PARTY = 'PayeeParty';
    /** UBL XML element local name 'PayerFinancialAccount'. */
    public const PAYER_FINANCIAL_ACCOUNT = 'PayerFinancialAccount';
    /** UBL XML element local name 'Party'. */
    public const PARTY = 'Party';
    /** UBL XML element local name 'PartyIdentification'. */
    public const PARTY_IDENTIFICATION = 'PartyIdentification';
    /** UBL XML element local name 'PartyLegalEntity'. */
    public const PARTY_LEGAL_ENTITY = 'PartyLegalEntity';
    /** UBL XML element local name 'PartyName'. */
    public const PARTY_NAME = 'PartyName';
    /** UBL XML element local name 'PartyTaxScheme'. */
    public const PARTY_TAX_SCHEME = 'PartyTaxScheme';
    /** UBL XML element local name 'PaymentMandate'. */
    public const PAYMENT_MANDATE = 'PaymentMandate';
    /** UBL XML element local name 'PaymentDueDate'. */
    public const PAYMENT_DUE_DATE = 'PaymentDueDate';
    /** UBL XML element local name 'PaymentID'. */
    public const PAYMENT_ID = 'PaymentID';
    /** UBL XML element local name 'PaymentMeans'. */
    public const PAYMENT_MEANS = 'PaymentMeans';
    /** UBL XML element local name 'PaymentMeansCode'. */
    public const PAYMENT_MEANS_CODE = 'PaymentMeansCode';
    /** UBL XML element local name 'PaymentTerms'. */
    public const PAYMENT_TERMS = 'PaymentTerms';
    /** UBL XML element local name 'PrepaidAmount'. */
    public const PREPAID_AMOUNT = 'PrepaidAmount';
    /** UBL XML element local name 'Percent'. */
    public const PERCENT = 'Percent';
    /** UBL XML element local name 'PostalAddress'. */
    public const POSTAL_ADDRESS = 'PostalAddress';
    /** UBL XML element local name 'PostalZone'. */
    public const POSTAL_ZONE = 'PostalZone';
    /** UBL XML element local name 'Price'. */
    public const PRICE = 'Price';
    /** UBL XML element local name 'PriceAmount'. */
    public const PRICE_AMOUNT = 'PriceAmount';
    /** UBL XML element local name 'PrimaryAccountNumberID'. */
    public const PRIMARY_ACCOUNT_NUMBER_ID = 'PrimaryAccountNumberID';
    /** UBL XML element local name 'ProfileID'. */
    public const PROFILE_ID = 'ProfileID';
    /** UBL XML element local name 'RegistrationName'. */
    public const REGISTRATION_NAME = 'RegistrationName';
    /** UBL XML element local name 'SalesOrderID'. */
    public const SALES_ORDER_ID = 'SalesOrderID';
    /** UBL XML element local name 'SellersItemIdentification'. */
    public const SELLERS_ITEM_IDENTIFICATION = 'SellersItemIdentification';
    /** UBL XML element local name 'StandardItemIdentification'. */
    public const STANDARD_ITEM_IDENTIFICATION = 'StandardItemIdentification';
    /** UBL XML element local name 'StartDate'. */
    public const START_DATE = 'StartDate';
    /** UBL XML element local name 'StreetName'. */
    public const STREET_NAME = 'StreetName';
    /** UBL XML element local name 'TaxAmount'. */
    public const TAX_AMOUNT = 'TaxAmount';
    /** UBL XML element local name 'TaxCurrencyCode'. */
    public const TAX_CURRENCY_CODE = 'TaxCurrencyCode';
    /** UBL XML element local name 'TaxCategory'. */
    public const TAX_CATEGORY = 'TaxCategory';
    /** UBL XML element local name 'TaxExemptionReason'. */
    public const TAX_EXEMPTION_REASON = 'TaxExemptionReason';
    /** UBL XML element local name 'TaxExemptionReasonCode'. */
    public const TAX_EXEMPTION_REASON_CODE = 'TaxExemptionReasonCode';
    /** UBL XML element local name 'TaxExclusiveAmount'. */
    public const TAX_EXCLUSIVE_AMOUNT = 'TaxExclusiveAmount';
    /** UBL XML element local name 'TaxInclusiveAmount'. */
    public const TAX_INCLUSIVE_AMOUNT = 'TaxInclusiveAmount';
    /** UBL XML element local name 'TaxPointDate'. */
    public const TAX_POINT_DATE = 'TaxPointDate';
    /** UBL XML element local name 'TaxScheme'. */
    public const TAX_SCHEME = 'TaxScheme';
    /** UBL XML element local name 'TaxSubtotal'. */
    public const TAX_SUBTOTAL = 'TaxSubtotal';
    /** UBL XML element local name 'TaxTotal'. */
    public const TAX_TOTAL = 'TaxTotal';
    /** UBL XML element local name 'TaxableAmount'. */
    public const TAXABLE_AMOUNT = 'TaxableAmount';
    /** UBL XML element local name 'TaxRepresentativeParty'. */
    public const TAX_REPRESENTATIVE_PARTY = 'TaxRepresentativeParty';
    /** UBL XML element local name 'Telephone'. */
    public const TELEPHONE = 'Telephone';
    /** UBL XML element local name 'URI'. */
    public const URI = 'URI';
    /** UBL XML element local name 'Value'. */
    public const VALUE = 'Value';
}
