**Elkarte development repository**

Elkarte is licensed under [BSD 3-clause license](http://www.opensource.org/licenses/BSD-3-Clause).

Documentation is licensed under [CC-by-SA 3](http://creativecommons.org/licenses/by-sa/3.0).

Third party libraries or sets of images, are under their own licenses.

Notes:
===
Feel free to fork this repository and make your desired changes.

Please see the [Developer's Certificate of Origin](https://github.com/elkarte/Elkarte/blob/master/DCO.txt) in the repository:
by signing off your contributions, you acknowledge that you can and do license your submissions under the license of the project.

How to contribute:
===
* Fork the repository. If you are not used to using Github, please check out [fork a repository](http://help.github.com/fork-a-repo).
* Branch your own repository. Example:
```
git checkout -b new_enhancement_name
```
  * Please do not commit to your 'master' branch, or branch off and cherry-pick commits before sending in a PR.
* Commit the desired changes to that branch. As many commits on 'new_enhancement' as you want.
  * Granular commits are not a problem.
  * Please try to add only commits related to the respective feature/fix to a branch.
  * If you need to commit something unrelated, try to create another branch for that topic.
* Sign-off on your commits, to acknowledge your submission under the license of the project.
* Send in a pull request (PR) from your _new_enhancement_name_ branch.
* If the main repository has been updated in the meantime, you can pull the changes in your repository:
  * to pull in from master to your master branch: ``` git pull --rebase upstream master ``` (please replace 'upstream' to the name of your remote for the main repository)
  * to merge after you have updated your master, to your _new_enhancement_name branch: ``` git rebase master ```

Details about the rebase operation:
[git rebase] (http://www.kernel.org/pub/software/scm/git/docs/git-rebase.html)

Recommended reading for github pull requests workflow:
[About Github workflow](http://qsapp.com/wiki/Github#Github_Contributor_Workflow)