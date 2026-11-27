# CEN EN 16931 UBL rules 1.3.17

- Upstream: [ConnectingEurope/eInvoicing-EN16931](https://github.com/ConnectingEurope/eInvoicing-EN16931/releases/tag/validation-1.3.17), tag `validation-1.3.17`, commit `6e35e0a` (released 2026-10-12).
- Source Schematron: upstream `schematron/preprocessed/EN16931-UBL-validation-preprocessed.sch`.
- Upstream UBL archive SHA-256: `BF02A0E3F6CBE692A4A28E70BE7BDCADED5612B4169B543E13D6CB4F33C29E92`.
- Compiler: SchXslt 1.10.1 `pipeline-for-svrl.xsl`, archive SHA-256 `E141FABD4804A0EA527945DC7253DD38F9EF8957D9B219B94E13D16859CFF7F0`.
- Runtime used to compile: bundled Saxon-HE 12.10 on Java 11.
- CEN rules are licensed under EUPL 1.2; see `notices/EUPL-1.2-en.pdf`. The generated XSLT uses SchXslt under the MIT license; see `SchXslt-LICENSE.txt`.

`CEN-EN16931-UBL.xsl` is generated from the preprocessed `.sch` file. Do not hand-edit the compiled stylesheet.
