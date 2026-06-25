# Travel System User Manual

Welcome to the HR Portal Travel System. This manual provides step-by-step instructions on how to use the **Travel Order Request** (`travel.php`) and **Apply Expense** (`Apply_expense.php`) modules.

---

## 1. Travel Order Request (`travel.php`)

The Travel Order Request form is used to submit a travel plan for approval before the travel occurs.

![Travel Order Form](images\travel_order_form_1782312198482.png)

### Step-by-Step Guide:

1. **Employee Information**: Provide your Employee ID. If you select your ID, the system will typically auto-fill or expect details like your Name, Branch Code, Level, Designation, and Department.
2. **Travel Details**:
   - **Travel From & Destination**: Enter your starting location and the final destination.
   - **Travel Dates**: Select the start date (`Date From`) and end date (`Date To`). The number of days will be calculated accordingly.
   - **Mode of Transport**: Specify how you will be traveling (e.g., Flight, Bus, Company Vehicle).
   - **Kilometer**: Enter the estimated travel distance in kilometers.
   - **Purpose**: Briefly explain the reason for the travel.
   - **Estimated Cost**: Enter the estimated cost for the trip.
3. **Approval Information**:
   - **Request Type**: Select whether this is a 'Normal' travel request or a 'Claim' (Note: Claim is only available for Branch Code 100).
   - **Approver Email**: Select the appropriate approver from the dropdown list. The system determines the necessary approval level (e.g., Department Head, Province Head, HR, CEO) based on your level and branch code.
4. **Document Upload**:
   - You must attach a supporting document (e.g., an invitation, schedule, or approval memo).
   - **Allowed Formats**: PDF, JPG, PNG.
   - **Max Size**: 5MB.
5. **Submit**: Click the **Submit** button. An email notification will automatically be sent to your assigned approver.

> [!TIP]
> Make sure to submit your travel request well in advance to allow time for the approval process.

---

## 2. Apply Travel Expense (`Apply_expense.php`)

After your travel is complete and your travel order was approved, you can claim your expenses using the Apply Expense form.

![Apply Expense Form](images\apply_expense_form_1782312209574.png)

### Step-by-Step Guide:

1. **Employee Search**:
   - Begin by typing your **Employee ID** or **Name** in the search bar. The system will search and display matching records. Select your profile to proceed.
2. **Link Travel Order**:
   - Once your profile is loaded, the system will fetch your approved Travel Orders that haven't been claimed yet.
   - Select the relevant Travel Order to link the expenses. This will auto-fill related fields like Purpose, Dates, and Vehicle based on the original request.
3. **Expense Details**:
   - Fill in the actual expenses incurred during the trip. This includes:
     - **Distance** and **Vehicle**
     - **Transportation Fare**, **Airport/Flight costs**, **Road Tax**
     - **Daily Allowance (Rate & Days)**
     - **Hotel Costs**
     - **Other Expenses**
     - **Advances** (if any were taken)
   - Add any additional **Remarks** if necessary.
4. **Document / Bill Upload**:
   - You are required to upload a single file containing scanned copies of your bills, receipts, and boarding passes.
   - **Allowed Formats**: PDF, JPG, JPEG, PNG.
5. **Submit & Manage**:
   - Click **Submit Expense**.
   - Below the form, you can view a table of your **Past Expense Records** along with their current status (e.g., Pending, Approved). You can also edit recently submitted expenses if changes are needed.

> [!IMPORTANT]
> Keep all physical receipts and bills. You must upload clear scans or photos of them to process the expense claim successfully.

---
*If you encounter any issues while using the system, please contact the HR or IT support team.*
