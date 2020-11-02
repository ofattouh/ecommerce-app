== WooCommerce Checkout Registration ==

This plugin adds functionality to WooCommerce to collect student data
on the Checkout page for products that are part of a category.

The student data will be added to the order notes and all order data will be inserted to a SQL database.

The number of student records collected depends on the quantity of the product added to the cart.
If one product is in the cart the Billing Name and email is used for the student record.
For each quantity over one a row of entry fields are added to a form on the checkout page.

When the order is completed the student record is added to the order details and also appended
to a MySQL table.
