# Touring REST API

> Laravel REST API for the touring app

## Table of Contents

## Commands

### Dump R2 Bucket

This will delete all files from the specified bucket. Needs the AWS CLI to be authenticated and with a configured
profile.

**Note: ** Remove the `--dry-run` flag to execute the command.

```bash
$ $ aws s3 rm s3://<bucket-name> --endpoint-url https://<cloudflare-id>.r2.cloudflarestorage.com --recursive --dryrun --profile r2
```
