# univie_pure
This is the Vienna pure extension for TYPO3 modified for LUH.

## What does it do?

Univie_pure shows publication/project/dataset data on TYPO3 pages using the Pure/FIS-API.


## API-Docs

See -> https://www.fis.uni-hannover.de/ws


## Precache person organisations and project data

To reduce API traffic and loadtime there is a cli symfony console command to precache static data for persons, organisations and project data.
> ./typo3cms univie_pure:importfis

