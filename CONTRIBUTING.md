# Contributing to Laravel-Faked

Thank you for considering contributing to **laravel-faked**! We welcome your help to improve this Laravel package. 

To ensure a smooth and productive workflow, please review the following guidelines before contributing.

---

## 🛠️ Code of Conduct
By participating in this project, you agree to abide by our [Code of Conduct](https://contributor-covenant.org/). Please be respectful, constructive, and professional in all interactions.

---

## 🐛 Reporting Bugs & Issues
If you encounter a bug or a problem, please search the existing [GitHub Issues](https://github.com/isocroft/laravel-faked/issues) first to see if it has already been reported.

If it hasn't, feel free to open a new issue with the following details:
*   **Clear Title**: A concise summary of the issue.
*   **Environment Details**: Your PHP version, Laravel version, and package version.
*   **Steps to Reproduce**: Detailed steps or a minimal code snippet to recreate the error.
*   **Expected vs. Actual Behavior**: What should have happened versus what actually happened.

---

## 💡 Proposing Features or Improvements
We love new ideas! If you want to propose a new feature:
1. Open an issue to discuss it first. This ensures nobody spends time working on a feature that doesn't align with the package's goals.
2. Clearly explain the problem the feature solves and how you envision the API/usage looking.

---

## 🚀 Development Workflow

To start working on this package locally, follow these steps:

### 1. Fork and Clone
Fork the repository on GitHub, then clone it to your local machine:
```bash
git clone https://github.com/isocroft/laravel-faked.git
cd [Package-Name]
```

### 2. Install Dependencies
Run Composer to install the development packages:
```bash
composer install
```

### 3. Coding Standards
We follow **PSR-12** coding standards. Before submitting your code, please run the code fixer to ensure consistency:
```bash
# If using Laravel Pint
./vendor/bin/pint

# If using PHP_CodeSniffer
./vendor/bin/phpcs
```

### 4. Running Tests
All pull requests must pass the existing test suite. Please add tests for any new features or bug fixes you write.
```bash
# If using Pest
./vendor/bin/pest

# If using PHPUnit
./vendor/bin/phpunit
```

---

## 🔀 Pull Request Process

When you are ready to submit your changes, please follow these guidelines:

1.  **Branching**: Create a descriptive feature branch (e.g., `feature/add-new-middleware` or `fix/validation-bug`). Do not work directly on the `main` or `master` branch.
2.  **Keep it Focused**: A pull request should do one thing. If you want to fix a bug and add a feature, please submit two separate pull requests.
3.  **Update Documentation**: If your change alters how the package is used, make sure to update the `README.md` or respective documentation files.
4.  **Open the PR**: Submit the pull request against the `main` (or development) branch of the original repository.
5.  **Describe Your Changes**: Fill out the PR template/description clearly, linking any related open issues (e.g., `Closes #123`).

---

## 📜 License
By contributing to this project, you agree that your contributions will be licensed under its **Apache-2.0 License**.
