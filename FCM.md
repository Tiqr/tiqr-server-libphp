# FCM voor Tiqr

## Prepare

- Log in to the [Google cloud console](https://console.cloud.google.com/)
- Create or select a project. Copy the project ID and enter it in the config as `firebase_projectId`

## Add a new role

- Go to `IAM & Admin` and select `roles` in the left menu
- Create a new role, and name it `CloudMessaging` in the title and id, set it to General Availibility.
- Assign two permissions: `cloudmessaging.messages.create` and `firebasecloudmessaging.messages.create` and click `Create`

## Add a new serviceaccount and key
- Go to `API's& Services`, and click `+ Enable API's and Services`
- Search for `firebase cloud messaging api` and enable it
- Next, go to `Credentials` and create a new credential. Choose for the service account, enter a name, and ID, and copy the email-address generated
- Open the newl user and navigate to the `keys` tab. Add a new key, and chose the json format. The downloaded file should be places in the config directory and configured in the config file as `firebase_credentialsFile`

## Grant permissions

- Open a cloud shell (Icon in the top-right corner).
- Assign the role to the user by running : `gcloud projects add-iam-policy-binding <projectID> --member=serviceAccount:<serviceaccount-email> --role=projects/<projectID/roles/CloudMessaging`


