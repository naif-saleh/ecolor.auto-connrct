# Laravel Console Commands

This project includes custom Laravel console commands for handling automated tasks related to ADial providers and calls.

## Available Commands

### 1. `ADialMakeCallCommand`

This command is responsible for initiating calls through the ADial system.

**Command Signature:**

```sh
php artisan app:ADial-make-call
```

#### Logging Structure:

- `[START]` ✅ ADialMakeCallCommand started at
- `[CHICK_IF_TIME_CONFIGURED]` ADialMakeCallCommand: ⚠️ Call time settings not configured. ⚠️
- `[GENERAL_TIME_NOT_CONFIGURED]` ADialMakeCallCommand: 📞🛑 Calls are not allowed at this time. ⏳❌
- `[GENERAL_TIME_IS_WITHEN]` ADialMakeCallCommand: ✅ Allowed call window:
- `[GENERAL_TIME_IS_NOT_WITHEN]` ADialMakeCallCommand: 📞🛑 Calls are not allowed at this time. ⏳❌
- `[PROVIDERS_COUNT]` ADialMakeCallCommand: Found
- `[FILE_IS_NOT_WITHEN]` ADialMakeCallCommand: 🛑 Skipping File ID
- `[CURRENT_CALLS]` ADialMakeCallCommand: Current active calls:
- `[ERROR_FETCH_ACTIVE_CALLS]` ADialMakeCallCommand: ❌ Error fetching active calls:
- `[COUNT_OF_CALLS_FOR_FEED]` ADialMakeCallCommand:Feed-count of
- `[IS_FEED_CALLS_DOEN]` ADialMakeCallCommand: ✅ All numbers called for File ID:
- `[SUCCESSFUL_CALL]` ADialMakeCallCommand: ✅ Call successful. Call ID:
- `[SUCCESSFUL_CALL_AND_SAVED]` ADialMakeCallCommand: ✅ Mobile Number Called and Saved Successfully:
- `[FAILED_CALL]` ADialMakeCallCommand: ❌ ADial MakeCall: Call Failed to number
- `[COMMAND_EXECUTED_DONE]` ADialMakeCallCommand: 📞✅ ADialMakeCallCommand execution completed.
---

### 2. `ADialParticipantsCommand`

This command processes active calls and updates call statuses from the 3CX API.

**Command Signature:**

```sh
php artisan app:ADial-participants-command
```

**Functionality:**

- Fetches ADial providers that have active feed files for the day.
- Determines if a provider should be checked based on active call time windows or ongoing calls.
- Uses the `ThreeCxService` to fetch active calls for each provider.
- Queues jobs to update call statuses asynchronously.
- Logs execution time, errors, and relevant updates.

#### Logging Structure:

- `[START]` ✅ ADialParticipantsCommand started at
- `[TOTAL_PROVIDERS]` ADialParticipantsCommand: Total providers found:
- `[PROVIDER_IS_PROCESSING]` ADialParticipantsCommand Processing provider:
- `[PROVIDER_PROCESS]` Processing a specific provider.
- `[PROVIDER_SKIP]` ADialParticipantsCommand ⚠️ No active feed files for provider:
- `[PROVIDER_SKIP_WITH_NO_ACTIVITY]` ADialParticipantsCommand ⚠️ Skipping provider no current activity
- `[PROVIDER_EXECUTION_TIME]` ADialParticipantsCommand⏳ Execution time for provider
- `[NO_CALL_ACTIVE_FOR_PROVIDER]` ADialParticipantsCommand ⚠️ No active calls found for provider
- `[CALL_FETCH]` ADialParticipantsCommand Found active call(s) for provider
- `[CALL_FETCH_SUCCESS]` Successfully retrieved active calls.
- `[ACTIVE_CALL_FETCH_FAILED]` ADialParticipantsCommand❌ Failed to process active calls for provider
- `[ACTIVE_CALL_ID_MISSING]` ADialParticipantsCommand ⚠️ Missing Call ID in response
- `[QUEUE_UPDATE]` ADialParticipantsCommand 📤 Queued update for Call ID:
- `[CALL_UPDATE_SUCCESS]` UpdateCallStatusJob✅ Updated Call:
- `[CALL_EXECUTION_TIME]`UpdateCallStatusJob⏳ Execution time for call
- `[CALL_UPDATE_FAILED]` UpdateCallStatusJob❌ Failed to update database for Call ID
- `[END_COMMAND]` ADialParticipantsCommand ✅ ADialParticipantsCommand execution completed in

---

Example search commands:

```sh
tail -f storage/logs/laravel-2025-03-03.log | grep -e "[END_COMMAND]"
```

```sh
tail -f storage/logs/laravel-2025-03-03.log | grep "[CALL_EXECUTION_TIME]"
```
