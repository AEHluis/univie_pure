#!/usr/bin/env python3
"""
GitLab Issues Reader
Reads issues from a GitLab project using an access token and prints them to console.
"""

import requests
import sys
import os
from pathlib import Path
from typing import List, Dict, Any, Optional


def read_gitlab_config(config_file: str = ".gitlab") -> Optional[Dict[str, str]]:
    """
    Read GitLab configuration from .gitlab file.

    Args:
        config_file: Path to the .gitlab configuration file

    Returns:
        Dictionary with configuration values or None if not found
    """
    try:
        config_path = Path(config_file)
        if not config_path.exists():
            print(f"Error: Configuration file '{config_file}' not found")
            return None

        config = {}
        with open(config_path, 'r') as f:
            for line in f:
                line = line.strip()
                if ':' in line and not line.startswith('#'):
                    key, value = line.split(':', 1)
                    config[key.strip()] = value.strip()

        # Check for required fields
        if 'access-token' not in config:
            print(f"Error: 'access-token' not found in {config_file}")
            return None

        if 'project_id' not in config:
            print(f"Error: 'project_id' not found in {config_file}")
            return None

        return config

    except Exception as e:
        print(f"Error reading configuration file: {e}")
        return None


def get_issue_comments(
    gitlab_url: str,
    project_id: str,
    issue_iid: int,
    access_token: str
) -> List[Dict[str, Any]]:
    """
    Fetch comments (notes) for a specific issue.

    Args:
        gitlab_url: Base URL of GitLab instance (e.g., 'https://gitlab.com')
        project_id: Project ID or 'namespace/project-name'
        issue_iid: Issue IID (internal ID)
        access_token: GitLab personal access token

    Returns:
        List of comment dictionaries
    """
    project_id_encoded = requests.utils.quote(project_id, safe='')
    api_url = f"{gitlab_url}/api/v4/projects/{project_id_encoded}/issues/{issue_iid}/notes"

    headers = {
        "PRIVATE-TOKEN": access_token
    }

    params = {
        "per_page": 100
    }

    try:
        response = requests.get(api_url, headers=headers, params=params)
        response.raise_for_status()
        return response.json()
    except requests.exceptions.RequestException as e:
        print(f"Error fetching comments for issue {issue_iid}: {e}")
        return []


def get_gitlab_issues(
    gitlab_url: str,
    project_id: str,
    access_token: str,
    state: str = "all"
) -> List[Dict[str, Any]]:
    """
    Fetch issues from a GitLab project.

    Args:
        gitlab_url: Base URL of GitLab instance (e.g., 'https://gitlab.com')
        project_id: Project ID or 'namespace/project-name'
        access_token: GitLab personal access token
        state: Issue state filter ('opened', 'closed', 'all')

    Returns:
        List of issue dictionaries
    """
    # URL encode the project_id if it contains slashes
    project_id_encoded = requests.utils.quote(project_id, safe='')

    api_url = f"{gitlab_url}/api/v4/projects/{project_id_encoded}/issues"

    headers = {
        "PRIVATE-TOKEN": access_token
    }

    params = {
        "state": state,
        "per_page": 100  # Get up to 100 issues per page
    }

    try:
        response = requests.get(api_url, headers=headers, params=params)
        response.raise_for_status()
        return response.json()
    except requests.exceptions.RequestException as e:
        print(f"Error fetching issues: {e}")
        sys.exit(1)


def print_issues(
    issues: List[Dict[str, Any]],
    gitlab_url: str,
    project_id: str,
    access_token: str
) -> None:
    """
    Print issues to console in a readable format.

    Args:
        issues: List of issue dictionaries
        gitlab_url: Base URL of GitLab instance
        project_id: Project ID or 'namespace/project-name'
        access_token: GitLab personal access token
    """
    if not issues:
        print("No issues found.")
        return

    print(f"\n{'=' * 80}")
    print(f"Found {len(issues)} issue(s)")
    print(f"{'=' * 80}\n")

    for i, issue in enumerate(issues, 1):
        print(f"Issue #{i}")
        print(f"  ID: {issue.get('iid')}")
        print(f"  Title: {issue.get('title')}")
        print(f"  State: {issue.get('state')}")
        print(f"  Author: {issue.get('author', {}).get('name', 'Unknown')}")
        print(f"  Created: {issue.get('created_at')}")
        print(f"  Updated: {issue.get('updated_at')}")

        labels = issue.get('labels', [])
        if labels:
            print(f"  Labels: {', '.join(labels)}")

        assignees = issue.get('assignees', [])
        if assignees:
            assignee_names = [a.get('name', 'Unknown') for a in assignees]
            print(f"  Assignees: {', '.join(assignee_names)}")

        print(f"  URL: {issue.get('web_url')}")

        # Print description if available
        description = issue.get('description', '')
        if description:
            # Truncate long descriptions
            desc_preview = description # + "..." if len(description) > 200 else description
            print(f"  Description: {desc_preview}")

        # Fetch and print comments
        comments = get_issue_comments(gitlab_url, project_id, issue.get('iid'), access_token)
        if comments:
            print(f"\n  Comments ({len(comments)}):")
            for j, comment in enumerate(comments, 1):
                author = comment.get('author', {}).get('name', 'Unknown')
                created = comment.get('created_at', '')
                body = comment.get('body', '')
                print(f"\n    Comment #{j} by {author} on {created}:")
                print(f"    {body}")

        print(f"\n{'-' * 80}\n")


def main():
    """
    Main function to read and display GitLab issues.
    """
    # Read configuration from .gitlab file
    config = read_gitlab_config(".gitlab")
    if not config:
        print("\nFallback: Trying environment variables...")
        # Fallback to environment variables
        GITLAB_URL = os.getenv("GITLAB_URL", "https://gitlab.com")
        PROJECT_ID = os.getenv("GITLAB_PROJECT_ID", "")
        ISSUE_STATE = os.getenv("GITLAB_ISSUE_STATE", "all")
        ACCESS_TOKEN = os.getenv("GITLAB_ACCESS_TOKEN", "")

        if not ACCESS_TOKEN:
            print("Error: Access token not found")
            print("Please ensure .gitlab file exists with 'access-token:' entry")
            print("Or set GITLAB_ACCESS_TOKEN environment variable")
            sys.exit(1)

        if not PROJECT_ID:
            print("Error: GITLAB_PROJECT_ID not set")
            print("Usage: export GITLAB_PROJECT_ID='your-project-id'")
            print("       (can be numeric ID or 'namespace/project-name')")
            sys.exit(1)
    else:
        # Use config from .gitlab file
        ACCESS_TOKEN = config['access-token']
        PROJECT_ID = config['project_id']
        GITLAB_URL = config.get('gitlab_url', 'https://gitlab.com')
        ISSUE_STATE = config.get('state', 'all')

        # Map 'open' to 'opened' for GitLab API compatibility
        if ISSUE_STATE == 'open':
            ISSUE_STATE = 'opened'

    print(f"Fetching issues from: {GITLAB_URL}")
    print(f"Project: {PROJECT_ID}")
    print(f"State filter: {ISSUE_STATE}\n")

    # Fetch and print issues
    issues = get_gitlab_issues(GITLAB_URL, PROJECT_ID, ACCESS_TOKEN, ISSUE_STATE)
    print_issues(issues, GITLAB_URL, PROJECT_ID, ACCESS_TOKEN)


if __name__ == "__main__":
    main()
