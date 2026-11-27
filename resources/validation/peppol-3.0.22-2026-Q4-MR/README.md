# Peppol BIS Billing Schematron 3.0.22

This directory contains the unmodified official UBL Schematron source for OpenPeppol's `2026-Q4-MR` release and the XSLT compiled from that source. The official release notes identify version 3.0.22, published on 2026-11-23 and mandatory from 2027-02-24.

The source was downloaded from [OpenPeppol's 2026-Q4-MR artifact](https://test-docs.peppol.eu/poacc/billing/3.0/2026-Q4-MR/files/PEPPOL-EN16931-UBL.sch). Its SHA-256 is `907FEAE87454F181CF364DA757A4E33D7C48AD9B72EF316601B364696524B230`.

The source includes the Q4-MR changes, including fatal severity for `PEPPOL-COMMON-R054`, `R055`, `R056-1`, `R056-2`, and `R057`; rules `R058`, `R059`, and `R062`; mandatory-cardinality rules `PEPPOL-EN16931-R140` through `R276`; and Slovak rules `SK-R-001` through `SK-R-007`. Do not apply local rule edits to this source.

`PEPPOL-EN16931-UBL.xsl` was compiled from the `.sch` file with SchXslt 1.10.1 (`2.0/pipeline-for-svrl.xsl`) and the package's Saxon-HE 12.10 runtime. The compiler distribution SHA-256 is `E141FABD4804A0EA527945DC7253DD38F9EF8957D9B219B94E13D16859CFF7F0`. The XSLT is generated; do not edit it by hand.

The Peppol package includes EN 16931 validation artefacts version 1.3.16. This connector separately runs its selected CEN UBL artefacts from `../cen-1.3.17/`; their versions are intentionally independent. EUPL 1.2 and SchXslt's MIT license are retained in this directory.

See the [official release notes](https://test-docs.peppol.eu/poacc/billing/3.0/2026-Q4-MR/release-notes/) and [validation resources guide](../README.md).
