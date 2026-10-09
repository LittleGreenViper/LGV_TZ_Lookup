**1.3.0** *October 9, 2026*

- Added Composer library packaging and lookup usage instructions. Boundary-file loading remains optional.
- Queries no longer load the JSON parser, and library connections preserve the application's execution time limit.

**1.2.0** *October 7, 2026*

- Used an LLM to optimize the project for lookup performance and memory footprint.

**1.1.1** *February 21 2026*

- Changed "Jerusalem" in the tests, to "Hebron."

**1.1.0** *September 30 2023*

- Switched to using PHP to pack the polygon, as MySQL has discontinued their polygon function. It's for the better, anyway. This is a lot faster.

**1.0.2** *June 22 2023*

- No code changes. Added docs directory.

**1.0.1** *June 21, 2023*

- Minor tweak that probably won't change anything. If one zone is smaller than another, it is checked first.

**1.0.0** *June 16, 2023*

- Initial Release
