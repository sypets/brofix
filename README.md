[![TYPO3 11](https://img.shields.io/badge/TYPO3-11-orange.svg)](https://get.typo3.org/version/11)
[![TYPO3 12](https://img.shields.io/badge/TYPO3-12-orange.svg)](https://get.typo3.org/version/12)
[![Crowdin](https://badges.crowdin.net/typo3-extension-brofix/localized.svg)](https://crowdin.com/project/typo3-extension-brofix)
[![CI Status](https://github.com/sypets/brofix/workflows/CI/badge.svg)](https://github.com/sypets/brofix/actions)
[![Downloads](https://img.shields.io/packagist/dt/sypets/brofix)](https://packagist.org/packages/sypets/brofix)

# TYPO3 extension `brofix`

Broken Link Fixer checks the links on your website, generates a report
and allows you to edit entries with broken links directly from the report
in the TYPO3 backend.

It can check all types of links: Links to pages, records, external URLs
and file links. This task can be executed in the TYPO3 backend via the
TYPO3 [Scheduler](https://docs.typo3.org/c/typo3/cms-scheduler/main/en-us/)
or via the command line and supports sending a status mail when broken
links are detected.

**Credit:** This extension is based on the TYPO3 system extension
[LinkValidator](https://docs.typo3.org/c/typo3/cms-linkvalidator/main/en-us),
it was forked from the source code. So it was based on earlier work.
Thanks go to the original authors and maintainers in the core, without
whom this work would not have been possible.

|                  | URL                                                |
|------------------|----------------------------------------------------|
| **Repository:**  | https://github.com/sypets/brofix                   |
| **Read online:** | https://docs.typo3.org/p/sypets/brofix/main/en-us/ |
| **TER:**         | https://extensions.typo3.org/extension/brofix      |

## Check Status Meanings

- **1 (RESULT_BROKEN):** The link is broken.
- **2 (RESULT_OK):** The link is working.
- **3 (RESULT_CANNOT_CHECK):** The link could not be checked (e.g., due to network issues).
- **4 (RESULT_EXCLUDED):** The link was excluded from checking.
- **5 (RESULT_UNKNOWN):** The link status is unknown because the server is identified as Cloudflare.
