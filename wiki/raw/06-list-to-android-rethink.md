## Rethink the Shopping List synchronization with mobile client.

The current implementation supports already the sync of data between web app and mobile client. Now sync all data with the mapping of categories.

I want to make UI/UX of Shopping List sync easier. 

*The vision:* 

The web app has menu item "Send to mobile" for is New lists. The list is marked as such that needs to be send. 

Client on start make request to get all sent lists and downloads them.

*What to do if there're multiple clients?*

The mobile app has also "Send to NextCloud" menu item for the isNew or isPurchased Shopping List.

Click sends list to nextcloud. On nextcloud list is created or updated.

### How to share list between users? 

Maybe "Send to Another user Mobile"

In this case, there must be some family groups i can share my lists to.

*How this family group feature should be designed? by addin emails or usernames?*


