git init
git branch -M main

# Remove existing remote if any, ignoring errors
git remote remove origin 2>$null

# Commit Henning
git add config/ login.php register.php logout.php choose_role.php generate_hash.php .htaccess includes/auth.php includes/.htaccess
git commit --author="Hening24 <kirigayamanda@gmail.com>" -m "feat(auth): implement core authentication logic and database config"

# Commit Rayhan
git add assets/ index.php 404.php includes/header.php includes/footer.php
git commit --author="mrayhang9o <mrayhang9@gmail.com>" -m "feat(ui): design main interface layout, header, footer, and styling"

# Commit Alex
git add task_tracker_db.sql dashboard.php includes/helpers.php
git commit --author="Alex-afa <alexispratamabahar2006@gmail.com>" -m "feat(db): implement database schema and dashboard queries"

# Commit Aufan
git add pages/member/
git commit --author="aufannnn <aufandamays4@gmail.com>" -m "feat(member): develop interactive frontend and member module logic"

# Commit Hafiyan
git add pages/admin/ pembagian_tugas_scrum.txt kredensial.txt
git commit --author="hafiyyandimas <hadiyyan8@gmail.com>" -m "docs(admin): add admin module features and scrum documentation"

# Commit remaining
git add .
git commit -m "chore: track any remaining unassigned files"

# Add remote and push
git remote add origin https://github.com/aufannnn/Task-Tracker-for-team.git
git push -u origin main
