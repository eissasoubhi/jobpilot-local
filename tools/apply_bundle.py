#!/usr/bin/env python3

from pathlib import Path
import subprocess

SOURCE_BRANCH = "fix/automatic-authorized-submission"
FILES = [
    "api/migrations/Version20260803233000.php",
    "api/src/Command/AutomaticSubmissionCommand.php",
    "api/src/Controller/GmailController.php",
    "api/src/Controller/JobController.php",
    "api/src/Entity/Application.php",
    "api/src/Entity/CvDocument.php",
    "api/src/Entity/JobOffer.php",
    "api/src/Entity/UserSettings.php",
    "api/src/Service/ApplicationEmailExtractor.php",
    "api/src/Service/AutomaticSubmissionService.php",
    "api/src/Service/GmailService.php",
    "api/src/Service/JobProcessor.php",
    "api/tests/Unit/ApplicationAutomaticSubmissionStateTest.php",
    "api/tests/Unit/ApplicationEmailExtractorTest.php",
    "docker-compose.yml",
    "initial-data/settings.json",
    "web/app/candidatures/page.tsx",
    "web/app/parametres/page.tsx",
    "web/lib/types.ts",
]


def run(*args: str) -> bytes:
    return subprocess.check_output(args)


def main() -> None:
    subprocess.run(
        ["git", "fetch", "origin", f"refs/heads/{SOURCE_BRANCH}:refs/remotes/origin/{SOURCE_BRANCH}"],
        check=True,
    )

    for filename in FILES:
        content = run("git", "show", f"origin/{SOURCE_BRANCH}:{filename}")
        path = Path(filename)
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_bytes(content)
        print(f"Copied {filename}")


if __name__ == "__main__":
    main()
