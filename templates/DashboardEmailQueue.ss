<p>
	<%t DashboardEmailQueue.Queued 'Queued emails' %>: <strong>$QueuedEmails.count</strong> (<%t DashboardEmailQueue.Newest 'newest' %>: $QueuedEmails.last.LastEdited.format('j.n.Y H:i:s'))<br>
	<%t DashboardEmailQueue.InProgress 'Emails in progress' %>: <strong>$EmailsInProgress.count</strong> (<%t DashboardEmailQueue.Newest 'newest' %>: $EmailsInProgress.last.LastEdited.format('j.n.Y H:i:s'))<br>
	<%t DashboardEmailQueue.Failed 'Failed emails' %>: <strong>$FailedEmails.count</strong> (<%t DashboardEmailQueue.Newest 'newest' %>: $FailedEmails.last.LastEdited.format('j.n.Y H:i:s'))<br>
	<%t DashboardEmailQueue.Succeeded 'Succeeded emails' %>: <strong>$SentEmails.count</strong> (<%t DashboardEmailQueue.Newest 'newest' %>: $SentEmails.last.LastEdited.format('j.n.Y H:i:s'))
</p>

<% if $AllEmails.first %>
	<% with $AllEmails.first %>
		<p>
			<%t DashboardEmailQueue.CountingStarted 'Counting started' %>: $Created.format('j.n.Y H:i') .
		</p>
	<% end_with %>
<% end_if %>

<p>
	<%t DashboardEmailQueue.QueueDescription 'Email messages in the queue are sent every {frequency} minutes (max {max_messages} messages at once). Only part of the email messages that the whole application/website sends is processed through the queue.' frequency=$EmailQueueFrequency max_messages=$EmailQueueMaxEmailMessages  %>
</p>


<% if $NewestEmails.count > 0 %>
	<h3><%t DashboardEmailQueue.NewestMessages 'Newest messages' %></h3>
	<table>
		<thead>
			<tr>
				<th><%t EmailQueue.ID 'ID' %></th>
				<th><%t EmailQueue.Created 'Created' %></th>
				<th><%t EmailQueue.LastEdited 'LastEdited' %></th>
				<th><%t EmailQueue.SendingSchedule 'Sending schedule' %>*</th>
				<th><%t EmailQueue.Status 'Status' %></th>
				<th><%t EmailQueue.ClassName 'Type' %></th>
				<th><%t EmailQueue.UniqueString 'Unique identidier' %></th>
			</tr>
		</thead>
		<tbody>
			<% loop $NewestEmails %>
				<tr>
					<td>$ID</td>
					<td>$Created</td>
					<td>$LastEdited</td>
					<td>$SendingSchedule</td>
					<td>$Status</td>
					<td>$EmailClass</td>
					<td>$UniqueString</td>
				</tr>
			<% end_loop %>
		</tbody>
	</table>
	<p>*) <%t DashboardEmailQueue.SendingScheduleDescription 'If the sending schedule is in the future and the status is "queued", the system will wait until the given time before the message will be sent.' %></p>
<% end_if %>

<style>
	td,th
	{
		padding: 2px;
	}
</style>